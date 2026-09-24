<?php

declare(strict_types=1);

// Запуск: php tests/Support/standalone-streaming-smoke.php /path/to/no-dev-checkout
use ApiSutra\Config\ClientConfig;
use ApiSutra\Tests\Stubs\Core\SequenceHttpClient;
use ApiSutra\Tests\Stubs\ProviderB\Requests\ProviderBDownloadRequest;
use ApiSutra\Tests\Stubs\Requests\BinaryUploadRequest;
use ApiSutra\Tests\Stubs\Retry\ConsumingHttpClient;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\VO\Files\FileInput;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

$checkout = $argv[1] ?? '';
if (!is_file($checkout . '/vendor/autoload.php')) {
    throw new RuntimeException('Укажите checkout с установленными --no-dev зависимостями');
}
require $checkout . '/vendor/autoload.php';
foreach (['Core/SequenceHttpClient', 'ProviderB/Requests/ProviderBDownloadRequest', 'Requests/BinaryUploadRequest', 'Retry/ConsumingHttpClient', 'TestClient'] as $stub) {
    require __DIR__ . '/../Stubs/' . $stub . '.php';
}
if (class_exists('Illuminate\Container\Container') || class_exists('GuzzleHttp\Client')) {
    throw new RuntimeException('В проверяемом checkout присутствуют dev-зависимости');
}
$factory = new HttpFactory();
$http = new ConsumingHttpClient([new Response(204)]);
$client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), new HttpTransport($http, $factory, $factory));
$source = Utils::streamFor('prefix:payload');
$source->seek(7);
$upload = (new BinaryUploadRequest(FileInput::fromStream($source, 'fixture')))->setClient($client)->send()->raw();
if (!$upload->isSuccess() || $http->bodies !== ['payload'] || !$source->isReadable()) {
    throw new RuntimeException('Нарушен standalone binary upload');
}
$http = new SequenceHttpClient([new Response(200, [], 'payload')]);
$client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), new HttpTransport($http, $factory, $factory));
$sink = Utils::streamFor('');
$download = (new ProviderBDownloadRequest('one'))->setClient($client)->withDownloadTo($sink)->sendAsync()->wait()->raw();
if (!$download->isSuccess() || $download->response->body !== null || $download->data->content() !== 'payload' || (string) $sink !== 'payload' || !$sink->isWritable()) {
    throw new RuntimeException('Нарушен standalone download');
}
$download->data->close();
echo "Standalone streaming: upload, download, sink и async работают без Laravel/Guzzle HTTP Client.\n";
