<?php

declare(strict_types=1);

// Запуск: php tests/Support/standalone-rate-limit-smoke.php /path/to/no-dev-checkout
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use ApiSutra\Exceptions\RateLimiting\RateLimitBackendException;
use ApiSutra\Exceptions\Request\RateLimitException;
use ApiSutra\Tests\Stubs\Core\SequenceHttpClient;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Stubs\Requests\JointQuotaRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\StrictCache;
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
foreach (['ArrayCache', 'StrictCache', 'VirtualClock'] as $support) {
    require __DIR__ . '/' . $support . '.php';
}
if (class_exists('Illuminate\Container\Container') || class_exists('GuzzleHttp\Client')) {
    throw new RuntimeException('В проверяемом checkout присутствуют dev-зависимости');
}
$factory = new HttpFactory();
$http = new SequenceHttpClient([new Response(200, [], '{}')]);
$clock = new VirtualClock();
$store = new StrictCache();
$client = new TestClient(new ClientConfig(
    baseUrl: 'https://fixture.test',
    rateLimit: new RateLimitConfig(limit: 1, behavior: RateLimitBehavior::Throw, store: $store),
    retry: new RetryConfig(attempts: 3, baseDelay: 0, maxDelay: 0, retryExceptions: [Throwable::class]),
), new HttpTransport($http, $factory, $factory), $clock, $clock);
if (!$client->send(new RetryPolicyRequest())->raw()->isSuccess()) {
    throw new RuntimeException('Первый запрос должен пройти через строгий store');
}
$result = $client->sendAsync(new RetryPolicyRequest())->wait()->raw();
if (!$result->exception instanceof RateLimitException || $result->response !== null
    || $result->errors->first()?->code !== ErrorCode::RateLimited
    || ($result->errors->first()?->context['retryAfter'] ?? null) !== 60 || $http->calls !== 1) {
    throw new RuntimeException('Нарушен контракт локального отказа');
}
$store->clear();
$store->failWrites = true;
$result = $client->send(new RetryPolicyRequest())->raw();
if (!$result->exception instanceof RateLimitBackendException || $result->response !== null
    || $result->errors->first()?->code !== ErrorCode::ExecutionError || $http->calls !== 1 || $clock->waits !== []) {
    throw new RuntimeException('Сбой store должен остановить отправку без retry');
}
echo "Standalone rate-limit: строгий PSR-16 store, локальный отказ и ошибка записи работают без Laravel/Guzzle HTTP Client.\n";

$lua = $checkout . '/src/RateLimiting/Resources/acquire.lua';
if (!is_file($lua) || !str_contains(file_get_contents($lua), 'MSET')) {
    throw new RuntimeException('В установленном пакете отсутствует Lua resource');
}
require __DIR__ . '/../Stubs/Requests/JointQuotaRequest.php';
$http = new SequenceHttpClient([new Response(200, [], '{}')]);
$client = new TestClient(new ClientConfig(
    baseUrl: 'https://fixture.test', rateLimit: new RateLimitConfig(1, 60, RateLimitBehavior::Throw),
), new HttpTransport($http, $factory, $factory), $clock, $clock);
if (!$client->send(new JointQuotaRequest())->raw()->isSuccess()
    || $client->send(new JointQuotaRequest())->raw()->errors->first()?->code !== ErrorCode::RateLimited
    || $http->calls !== 1) {
    throw new RuntimeException('Совместные квоты не работают в установленном пакете');
}
echo "Standalone joint quotas и Lua resource — OK.\n";
