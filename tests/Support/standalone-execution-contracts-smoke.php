<?php

declare(strict_types=1);

// Запуск: php tests/Support/standalone-execution-contracts-smoke.php /path/to/no-dev-checkout
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Transport\ConnectionException;
use ApiSutra\Tests\Stubs\Core\PsrNetworkFailure;
use ApiSutra\Tests\Stubs\Core\SequenceHttpClient;
use ApiSutra\Tests\Stubs\Requests\ConditionalRetryRequest;
use ApiSutra\Tests\Stubs\Requests\RawStringRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Timing\ExecutionDeadline;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\Transport\TransportExceptionNormalizer;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

$checkout = $argv[1] ?? '';
require $checkout . '/vendor/autoload.php';
foreach (['Core/PsrNetworkFailure', 'Core/SequenceHttpClient', 'Requests/RetryPolicyRequest', 'Requests/ConditionalRetryRequest', 'Requests/RawStringRequest', 'TestClient'] as $stub) {
    require __DIR__ . '/../Stubs/' . $stub . '.php';
}
require __DIR__ . '/VirtualClock.php';
if (class_exists('Illuminate\Container\Container') || class_exists('GuzzleHttp\Client')) {
    throw new RuntimeException('Проверка требует установки без dev-зависимостей');
}
$clock = new VirtualClock();
$factory = new HttpFactory();
$http = new SequenceHttpClient([
    new Response(429, ['Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', $clock->unixTime() + 1)], '{}'),
    new Response(200, ['Content-Type' => 'application/xml'], '<fixture/>'),
    new Response(200, ['Content-Type' => 'application/json'], 'null'),
]);
$client = new TestClient(new ClientConfig(
    baseUrl: 'https://fixture.test', retry: new RetryConfig(baseDelay: 0, jitter: false),
), new HttpTransport($http, $factory, $factory), $clock, $clock);
$request = (new ConditionalRetryRequest(HttpMethod::POST))->withRawResponse()
    ->withDeadline(ExecutionDeadline::afterMs(5000, $clock));
$result = $client->send($request)->raw();
if ($result->data !== '<fixture/>' || $clock->waits !== [1000] || $http->calls !== 2) {
    throw new RuntimeException('Нарушена композиция deadline/raw/safety/Retry-After без dev-зависимостей');
}
$expired = (new RawStringRequest())->withDeadline(ExecutionDeadline::afterMs(0, $clock));
$result = $client->send($expired)->raw();
if (($result->errors->first()?->context['reason'] ?? null) !== 'execution_deadline_exceeded' || $http->calls !== 2) {
    throw new RuntimeException('Истёкший срок должен остановить HTTP');
}
if ($client->send($expired->withoutDeadline())->raw()->data !== 'null') {
    throw new RuntimeException('Снятие дедлайна должно сохранять RawResponse');
}
$failure = TransportExceptionNormalizer::normalize(new PsrNetworkFailure(new Request('GET', 'https://fixture.test')));
if (!$failure instanceof ConnectionException || $failure->transmissionState->value !== 'unknown') {
    throw new RuntimeException('PSR network failure должен нормализоваться без Guzzle Client');
}
echo "Standalone execution contracts: deadline, raw, safety, Retry-After и PSR-only работают.\n";
