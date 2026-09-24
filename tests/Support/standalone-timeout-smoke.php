<?php

declare(strict_types=1);

// Запуск: php tests/Support/standalone-timeout-smoke.php /path/to/no-dev-checkout
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Tests\Stubs\Core\SequenceHttpClient;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Transport\HttpTransport;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;

$checkout = $argv[1] ?? '';
if (!is_file($checkout . '/vendor/autoload.php')) {
    throw new RuntimeException('Укажите checkout с установленными --no-dev зависимостями');
}
require $checkout . '/vendor/autoload.php';
foreach (['Core/SequenceHttpClient', 'Requests/RetryPolicyRequest', 'TestClient'] as $stub) {
    require __DIR__ . '/../Stubs/' . $stub . '.php';
}
require __DIR__ . '/VirtualClock.php';
if (class_exists('Illuminate\Container\Container') || class_exists('GuzzleHttp\Client')) {
    throw new RuntimeException('В проверяемом checkout присутствуют dev-зависимости');
}
$factory = new HttpFactory();
$http = new SequenceHttpClient([new Response(204), new Response(503, ['Retry-After' => '2'])]);
$clock = new VirtualClock();
$client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', delay: 750, retry: new RetryConfig(totalTimeoutMs: 1000)),
    new HttpTransport($http, $factory, $factory), $clock, $clock);
$request = (new RetryPolicyRequest())->setClient($client);
$result = $request->withoutRetry()->send()->raw();
if (!$result->isSuccess() || $http->options[0]->timeoutMs !== 250 || $http->options[0]->connectTimeoutMs !== 250) {
    throw new RuntimeException('Не передан остаток бюджета в HTTP-адаптер');
}
$result = $request->withoutDelay()->send()->raw();
if (!$result->exception instanceof ExecutionDeadlineException || $result->response?->status !== 503 || $http->calls !== 2 || $clock->waits !== [750]) {
    throw new RuntimeException('Нарушена остановка Retry-After по общему бюджету');
}
echo "Standalone timeout smoke: опции, deadline и ожидания работают без Laravel/Guzzle HTTP Client.\n";
