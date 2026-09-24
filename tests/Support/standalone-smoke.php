<?php

declare(strict_types=1);

// Запуск: php tests/Support/standalone-smoke.php /path/to/no-dev-checkout
use ApiSutra\Auth\ApiKeyAuthenticator;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\ArrayCache;
use ApiSutra\Transport\MockTransport;

$checkout = $argv[1] ?? '';
if (!is_file($checkout . '/vendor/autoload.php')) {
    fwrite(STDERR, "Укажите checkout с установленными --no-dev зависимостями.\n");
    exit(2);
}
require $checkout . '/vendor/autoload.php';
// Фикстуры подключаются явно: autoload-dev в проверяемом пакете отсутствует.
require __DIR__ . '/ArrayCache.php';
require __DIR__ . '/../Stubs/TestClient.php';
require __DIR__ . '/../Stubs/Requests/CacheProbeRequest.php';

if (class_exists('Illuminate\Container\Container') || class_exists('GuzzleHttp\Client')) {
    throw new RuntimeException('В проверяемом checkout присутствуют dev-зависимости');
}
$store = new ArrayCache();
$config = new ClientConfig(
    baseUrl: 'https://example.test',
    auth: new ApiKeyAuthenticator('fixture-secret', header: 'Authorization'),
    cacheConfig: new CacheConfig(store: $store),
    debug: true,
);
$transport = new MockTransport();
$transport->fake([CacheProbeRequest::class => MockResponse::sequence([
    MockResponse::success(['value' => 1]),
    MockResponse::success(['value' => 2]),
    MockResponse::success(['value' => 3]),
])]);
$client = new TestClient($config, $transport);
$request = (new CacheProbeRequest())->setClient($client);
if (
    $request->dataOrFail() !== ['value' => 1]
    || $request->dataOrFail() !== ['value' => 1]
    || $request->withCacheScope('account-b')->dataOrFail() !== ['value' => 2]
) {
    throw new RuntimeException('Нарушена изоляция кеша');
}
$safeDebug = $request->send()->raw()->requestDebugJson();
if ($safeDebug === null || str_contains($safeDebug, 'fixture-secret')) {
    throw new RuntimeException('Не получен безопасный debug export');
}
$client->clearCache();
if ($request->dataOrFail() !== ['value' => 3]) {
    throw new RuntimeException('Очистка пространства не сработала');
}
echo "Standalone smoke: кеш, очистка и redaction работают без Illuminate и Guzzle HTTP Client.\n";
