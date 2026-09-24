<?php

declare(strict_types=1);

// Запуск: php tests/Support/standalone-body-smoke.php /path/to/no-dev-checkout
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Tests\Stubs\Retry\ConsumingHttpClient;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\VO\Http\PreparedRequest;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

$checkout = $argv[1] ?? '';
if (!is_file($checkout . '/vendor/autoload.php')) {
    throw new RuntimeException('Укажите checkout с установленными --no-dev зависимостями');
}
require $checkout . '/vendor/autoload.php';
require __DIR__ . '/../Stubs/Retry/ConsumingHttpClient.php';
if (class_exists('Illuminate\Container\Container') || class_exists('GuzzleHttp\Client')) {
    throw new RuntimeException('В проверяемом checkout присутствуют dev-зависимости');
}
$factory = new HttpFactory();
$http = new ConsumingHttpClient([new Response(204), new Response(204), new Response(204)]);
$transport = new HttpTransport($http, $factory, $factory);
$original = new PreparedRequest(
    HttpMethod::POST, 'https://fixture.test', headers: ['Content-Length' => '8'],
    stream: Utils::streamFor('old-file'),
);
$transport->send($original->with(body: 'new'));
$transport->send($original->withoutBody());
$transport->send($original->withBody('old')->withStream(Utils::streamFor('stream')));
if ($http->bodies !== ['new', '', 'stream'] || $original->stream->tell() !== 0) {
    throw new RuntimeException('Нарушен контракт замены тела');
}
try {
    $transport->send($original->with(body: 'new', headers: ['Content-Length' => '99']));
    throw new RuntimeException('Неверная длина не отклонена');
} catch (ConfigurationException) {
    if (count($http->bodies) !== 3) {
        throw new RuntimeException('Неверное тело дошло до HTTP');
    }
}
echo "Standalone body: замена, очистка и framing работают без Laravel/Guzzle HTTP Client.\n";
