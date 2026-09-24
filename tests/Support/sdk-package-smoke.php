<?php

declare(strict_types=1);

use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use Composer\InstalledVersions;
use Example\Records\Config\ClientConfigFactory;
use Example\Records\DemoClient;
use Example\Records\Resources\Records\Get\GetRecordRequest;
use Example\Records\Resources\Records\Get\GetRecordResponseDto;

$application = $argv[1];
require $application . '/vendor/autoload.php';
if (
    InstalledVersions::isInstalled('apisutra/laravel')
    || class_exists('Illuminate\\Container\\Container')
    || class_exists('Example\\Records\\Laravel\\DemoServiceProvider', false)
) {
    throw new RuntimeException('Standalone-установка загрузила Laravel');
}
$transport = new MockTransport();
$transport->preventStrayRequests();
$transport->fake([GetRecordRequest::class => MockResponse::success(['data' => [
    'record_id' => 7, 'title' => 'Composer', 'created_at' => '2026-09-16T12:00:00+00:00', 'new_field' => false,
]])]);
$client = new DemoClient(ClientConfigFactory::create(), $transport);
$record = $client->records()->get(7)->send()->dataOrFail();
if (!$record instanceof GetRecordResponseDto || $record->id !== 7 || $record->_extra !== ['new_field' => false]) {
    throw new RuntimeException('Установленный SDK потерял клиент или DTO');
}
$sdk = InstalledVersions::getInstallPath('example/records-sdk');
$class = new ReflectionClass(DemoClient::class);
if (realpath((string) $class->getFileName()) !== realpath($sdk . '/src/DemoClient.php')) {
    throw new RuntimeException('Пример подключён не через установленный SDK');
}
// Дополнительно проверяем команду запуска, указанную в руководства SDK.
ob_start();
require $sdk . '/run.php';
$output = json_decode((string) ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
if ($output['id'] !== 7 || $output['status'] !== 404) {
    throw new RuntimeException('Не работает run.php установленного SDK');
}
echo "Installed SDK without Laravel — OK.\n";
