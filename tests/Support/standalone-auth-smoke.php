<?php

declare(strict_types=1);

// Запуск: php tests/Support/standalone-auth-smoke.php /path/to/no-dev-checkout
use ApiSutra\Auth\TokenAuthenticator;
use ApiSutra\Auth\BearerAuthenticator;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Pipeline\Auth\AuthRefreshLock;
use ApiSutra\Tests\Stubs\Requests\AuthRequest;
use ApiSutra\Tests\Stubs\Requests\TokenLoginRequest;
use ApiSutra\Tests\Stubs\Retry\ConsumingHttpClient;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\ArrayCache;
use ApiSutra\Tests\Support\StrictCache;
use ApiSutra\Tests\Support\TestAuthLockProvider;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Transport\HttpTransport;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;

$checkout = $argv[1] ?? '';
if (!is_file($checkout . '/vendor/autoload.php')) {
    throw new RuntimeException('Укажите checkout с установленными --no-dev зависимостями');
}
require $checkout . '/vendor/autoload.php';
foreach (['Requests/AuthRequest', 'Requests/TokenLoginRequest', 'Dto/TokenLoginResponse', 'Dto/SimpleResponseDto', 'Retry/ConsumingHttpClient', 'TestClient'] as $stub) {
    require __DIR__ . '/../Stubs/' . $stub . '.php';
}
foreach (['ArrayCache', 'StrictCache', 'TestAuthLockProvider', 'VirtualClock'] as $support) {
    require __DIR__ . '/' . $support . '.php';
}
if (class_exists('Illuminate\Container\Container') || class_exists('GuzzleHttp\Client')) {
    throw new RuntimeException('В проверяемом checkout присутствуют dev-зависимости');
}
$factory = new HttpFactory();
$store = new StrictCache();
$auth = new TokenAuthenticator('fixture:user', 'password', refreshRequestClass: TokenLoginRequest::class);
foreach (['a', 'b'] as $name) {
    $http = new ConsumingHttpClient([
        new Response(200, ['Content-Type' => 'application/json'], json_encode(['accessToken' => 'token-' . $name, 'expiresIn' => 600], JSON_THROW_ON_ERROR)),
        new Response(200, ['Content-Type' => 'application/json'], '{"id":1,"name":"fixture"}'),
        new Response(200, ['Content-Type' => 'application/json'], '{"id":1,"name":"fixture"}'),
    ]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://' . $name . '.fixture.test', auth: $auth, cacheConfig: new CacheConfig(store: $store)), new HttpTransport($http, $factory, $factory));
    $request = (new AuthRequest('fixture'))->withoutCache();
    if (!$client->send($request)->raw()->isSuccess() || !$client->sendAsync($request)->wait()->raw()->isSuccess()
        || count($http->requests) !== 3 || $http->requests[2]->getHeaderLine('Authorization') !== 'Bearer token-' . $name) {
        throw new RuntimeException('Нарушена изоляция токенов standalone');
    }
}
$lock = new AuthRefreshLock(new ArrayCache());
$owner = $lock->acquire('fixture', 5);
$lock->release('fixture', $owner);
if ($lock->acquire('fixture', 5) === null) {
    throw new RuntimeException('Локальная блокировка не освобождена');
}
$clock = new VirtualClock();
$locks = new TestAuthLockProvider($clock);
$locks->busy = true;
$http = new ConsumingHttpClient([]);
$client = new TestClient(new ClientConfig(
    baseUrl: 'https://fixture.test', timeout: 5, auth: $auth, cacheConfig: new CacheConfig(locks: $locks),
), new HttpTransport($http, $factory, $factory), $clock, $clock);
$result = (new AuthRequest('fixture'))->setClient($client)->sendAsync()->wait()->raw();
if ($result->errors->first()?->context['reason'] !== 'auth_refresh_lock_timeout' || $http->requests !== []) {
    throw new RuntimeException('Нарушен контракт ожидания auth lock');
}
echo "Standalone auth: token isolation, refresh, PSR-16, lease и async работают без Laravel/Guzzle HTTP Client.\n";

$http = new ConsumingHttpClient([new Response(401, [], 'original')]);
$client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', auth: new BearerAuthenticator('fixture')), new HttpTransport($http, $factory, $factory));
$result = $client->send(new AuthRequest('fixture'))->raw();
if (count($http->requests) !== 1 || $result->response?->body !== 'original') {
    throw new RuntimeException('Постоянный токен не должен разрешать auth retry');
}
