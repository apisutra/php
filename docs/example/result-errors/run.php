<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\ResultExceptionConfig;
use ApiSutra\Exceptions\Request\RequestException;
use ApiSutra\Exceptions\Serialization\ResponseTypeMismatchException;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use Example\ResultErrors\BrokenResponseExtension;
use Example\ResultErrors\DemoClient;
use Example\ResultErrors\GetAccountRequest;
use Example\ResultErrors\GetOwnerRequest;
use Example\ResultErrors\ProviderException;
use Example\ResultErrors\ProviderExceptionFactory;

require __DIR__ . '/../sdk/bootstrap.php';

$transport = new MockTransport();
$transport->preventStrayRequests();
$transport->fake([
    GetAccountRequest::class => MockResponse::success(['id' => 7]),
    GetOwnerRequest::class => MockResponse::success(['id' => 8]),
]);

// Успех не требует настройки исключений и ручной проверки типа.
$base = new ClientConfig(baseUrl: 'https://api.example.test');
$client = new DemoClient($base, $transport);
$observed = ['success' => [$client->account()->id, $client->owner()->id]];

// Расширение здесь намеренно нарушает Returns: защита работает без фабрики.
$broken = $base->with(extensions: [new BrokenResponseExtension()]);
try {
    (new DemoClient($broken, $transport))->account();
} catch (ResponseTypeMismatchException $error) {
    $observed['default'] = $error->reason;
}

// Одна фабрика на клиент; сообщения остаются рядом с отдельными запросами.
$configured = $broken->with(resultExceptions: new ResultExceptionConfig(
    exceptionFactory: new ProviderExceptionFactory(),
));
$client = new DemoClient($configured, $transport);
foreach (['account', 'owner'] as $operation) {
    try {
        $client->{$operation}();
    } catch (ProviderException $error) {
        $observed['custom'][$operation] = $error->getMessage();
    }
}

// HTTP-ошибку фабрика не обрабатывает и возвращает null: выбрасывается исходная.
$transport->fake([GetAccountRequest::class => MockResponse::make(['error' => 'denied'], 403)]);
$raw = $client->send(new GetAccountRequest())->raw();
try {
    $raw->throw();
} catch (RequestException $error) {
    $observed['fallback'] = ['status' => $raw->response?->status, 'original' => $error === $raw->exception];
}

echo json_encode($observed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
