<?php

declare(strict_types=1);

// Запуск: php tests/Support/standalone-retry-smoke.php /path/to/no-dev-checkout
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Tests\Stubs\Requests\MultipartUploadRequest;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Stubs\Retry\ConsumingHttpClient;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\FakeSleeper;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\VO\Files\FileInput;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

$checkout = $argv[1] ?? '';
if (!is_file($checkout . '/vendor/autoload.php')) {
    throw new RuntimeException('Укажите checkout с установленными --no-dev зависимостями');
}
require $checkout . '/vendor/autoload.php';
foreach (['Requests/MultipartUploadRequest', 'Requests/RetryPolicyRequest', 'Retry/ConsumingHttpClient', 'TestClient'] as $stub) {
    require __DIR__ . '/../Stubs/' . $stub . '.php';
}
require __DIR__ . '/FakeSleeper.php';
require __DIR__ . '/VirtualClock.php';
if (class_exists('Illuminate\Container\Container') || class_exists('GuzzleHttp\Client')) {
    throw new RuntimeException('В проверяемом checkout присутствуют dev-зависимости');
}
$factory = new HttpFactory();
$http = new ConsumingHttpClient([new Response(503)]);
$client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig()),
    new HttpTransport($http, $factory, $factory), new FakeSleeper());
$result = (new RetryPolicyRequest(HttpMethod::POST))->setClient($client)->send()->raw();
if (count($http->requests) !== 1 || ($result->errors->first()?->context['retryRefusalReason'] ?? null) !== 'operation_not_safe') {
    throw new RuntimeException('Нарушена стандартная политика безопасности POST');
}
foreach ([true, false] as $seekable) {
    $http = new ConsumingHttpClient([new Response(503, ['Retry-After' => '2']), new Response(200, [], '{}')]);
    $clock = new VirtualClock();
    $sleeper = new FakeSleeper($clock);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(
        attempts: 2, baseDelay: 20, jitter: false, safeMethods: [HttpMethod::POST],
    )), new HttpTransport($http, $factory, $factory), $sleeper, $clock);
    $stream = Utils::streamFor('prefix:fixture bytes');
    $stream->seek(7);
    $file = FileInput::fromStream($seekable ? $stream : new NoSeekStream($stream), 'fixture.txt');
    $result = (new MultipartUploadRequest([$file], 'fixture'))->setClient($client)->send()->raw();
    if ($seekable) {
        if (!$result->isSuccess() || count($http->bodies) !== 2 || $http->bodies[0] !== $http->bodies[1]
            || str_contains($http->bodies[0], 'prefix:') || $sleeper->totalMs !== 2000) {
            throw new RuntimeException('Нарушены восстановление multipart или объединение ожиданий');
        }
    } elseif (count($http->bodies) !== 1 || $sleeper->calls !== 0
        || ($result->errors->first()?->context['retryRefusalReason'] ?? null) !== 'body_not_replayable') {
        throw new RuntimeException('Нарушен отказ в повторе non-seekable тела');
    }
}
echo "Standalone retry smoke: политика, multipart и Retry-After работают без Laravel/Guzzle HTTP Client.\n";
