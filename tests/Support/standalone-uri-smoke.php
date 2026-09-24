<?php

declare(strict_types=1);

// Запуск: php tests/Support/standalone-uri-smoke.php /path/to/no-dev-checkout
use ApiSutra\Auth\ApiKeyAuthenticator;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Serialization\BooleanFormat;
use ApiSutra\Tests\Stubs\Requests\UriQueryRequest;
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
foreach (['Requests/RetryPolicyRequest', 'Requests/UriQueryRequest', 'Retry/ConsumingHttpClient', 'TestClient'] as $stub) {
    require __DIR__ . '/../Stubs/' . $stub . '.php';
}
if (class_exists('Illuminate\Container\Container') || class_exists('GuzzleHttp\Client')) {
    throw new RuntimeException('В проверяемом checkout присутствуют dev-зависимости');
}
$http = new ConsumingHttpClient([new Response(204)]);
$factory = new HttpFactory();
$client = new TestClient(new ClientConfig(
    baseUrl: 'https://fixture.test/v1?x=%2F&x=+',
    auth: new ApiKeyAuthenticator('fixture-key', header: null, query: 'api_key'),
    textBooleanFormat: BooleanFormat::Literal,
), new HttpTransport($http, $factory, $factory));
$result = (new UriQueryRequest(false, '/items?x=%20#fragment'))->setClient($client)->send()->raw();
if (!$result->isSuccess() || $http->requests[0]->getRequestTarget() !== '/v1/items?x=%2F&x=+&x=%20&value=false&included=&api_key=fixture-key') {
    throw new RuntimeException('Нарушен контракт URI или текстового boolean');
}
$invalid = (new UriQueryRequest(['key' => 'value']))->setClient($client)->send()->raw();
if ($invalid->errors->first()?->code->value !== 'serialization_error' || count($http->requests) !== 1) {
    throw new RuntimeException('Неподдерживаемая query-структура дошла до HTTP');
}
echo "Standalone URI smoke: query, boolean, auth и ошибки работают без Laravel/Guzzle HTTP Client.\n";
