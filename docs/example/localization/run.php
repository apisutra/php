<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use Example\Records\DemoClient;
use Example\Records\Resources\Records\Get\GetRecordRequest;

require __DIR__ . '/../sdk/bootstrap.php';

$output = [];
foreach (['en', 'ru'] as $locale) {
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    // Ошибка формы входа воспроизводится без обращения к внешнему API.
    $transport->fake([GetRecordRequest::class => MockResponse::success(['record_id' => []])]);
    $client = new DemoClient(new ClientConfig(
        baseUrl: 'https://records.example.test',
        localization: $locale,
    ), $transport);
    $result = $client->records()->get(7)->send()->raw();
    $output[$locale] = ['code' => $result->errors->first()?->code->value, 'message' => $result->message()];
}

$localization = new LocalizationConfig('ru', messages: [
    'en' => ['records.limit' => 'Limit exceeded: {limit}'],
    'ru' => ['records.limit' => 'Превышен лимит: {limit}'],
]);
$error = new ConfigurationException(new Message('records.limit', ['limit' => 10]));
$output['sdk'] = $error->localized($localization)->getMessage();
// Это строка внешнего владельца: каталог не изменяет её по совпадению текста.
$output['literal'] = (new ConfigurationException('Provider-specific message'))->localized($localization)->getMessage();

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
