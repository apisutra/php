<?php

declare(strict_types=1);

// Запуск: php tests/Support/standalone-external-url-smoke.php /path/to/no-dev-checkout
use ApiSutra\Auth\BearerAuthenticator;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use ApiSutra\Tests\Stubs\Retry\ConsumingHttpClient;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\HttpTransport;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;

$checkout = $argv[1] ?? '';
if (!is_file($checkout . '/vendor/autoload.php')) {
    throw new RuntimeException('Укажите checkout с установленными --no-dev зависимостями');
}
require $checkout . '/vendor/autoload.php';
foreach (['Requests/CacheProbeRequest', 'Retry/ConsumingHttpClient', 'TestClient'] as $stub) {
    require __DIR__ . '/../Stubs/' . $stub . '.php';
}
if (class_exists('Illuminate\Container\Container') || class_exists('GuzzleHttp\Client')) {
    throw new RuntimeException('В проверяемом checkout присутствуют dev-зависимости');
}
$http = new ConsumingHttpClient([new Response(204)]);
$factory = new HttpFactory();
$client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: new BearerAuthenticator('fixture-token')), new HttpTransport($http, $factory, $factory));
$result = (new CacheProbeRequest())->setClient($client)->withUrl('https://storage.test/%2f?x=+&x=%20&')->sendAsync()->wait()->raw();
if (!$result->isSuccess() || $http->requests[0]->getRequestTarget() !== '/%2f?x=+&x=%20&' || $http->requests[0]->hasHeader('Authorization')) {
    throw new RuntimeException('Нарушена передача полного URL или изоляция credentials');
}
echo "Standalone external URL smoke: target, origin policy и async работают без Laravel/Guzzle HTTP Client.\n";
