<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\Result\ResolvedResultFactory;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use Example\ClientShowcase\DemoClient;
use Example\ClientShowcase\Resources\Records\Get\GetRecordRequest;
use Example\ClientShowcase\Resources\Records\Save\SaveRecordRequest;
use Example\Continuation\TokenExtractor;
use Example\CustomResult\SdkResult;
use Example\CustomResult\SdkResultFactory;

require __DIR__ . '/../sdk/bootstrap.php';

// Готовый учебный SDK и локальные ответы; сетевых запросов нет.
$transport = new MockTransport();
$transport->preventStrayRequests();
$transport->fake([
    GetRecordRequest::class => MockResponse::sequence([
        MockResponse::success([
            'data' => ['id' => 7, 'title' => 'Первая запись'],
            'operationToken' => 'demo-operation-7',
        ]),
        MockResponse::make(['message' => 'Unauthorized'], 401),
    ]),
    SaveRecordRequest::class => MockResponse::success(['saved' => true]),
]);

$base = new ClientConfig(
    baseUrl: 'https://api.example.test',
    authRetryOn401: false,
    continuationTokenExtractor: new TokenExtractor(),
);

// Собственная фабрика явно получает настройки стандартного представления.
$defaults = new ResolvedResultFactory(
    mapper: $base->errorMapper,
    errorContextFactory: $base->errorContextFactory,
    continuationTokenExtractor: $base->continuationTokenExtractor,
);
$client = new DemoClient(
    $base->with(resolvedResultFactory: new SdkResultFactory($defaults)),
    $transport,
);

$handle = $client->records()->get(7)->send();
$resolved = $handle->resolved(); // Объявленный тип — ResolvedResultInterface.
if (!$resolved instanceof SdkResult) {
    throw new LogicException('Клиент должен использовать SdkResultFactory');
}
// После проверки IDE видит методы SdkResult и конкретный тип RecordDto.
$record = $resolved->recordOrFail();
$needsLogin = $resolved->requiresReauthorization();

$observed['success'] = [
    'resultClass' => $resolved::class,
    'dtoClass' => $record::class,
    'id' => $record->id,
    'title' => $record->title,
    'isSuccess' => $resolved->isSuccess(),
    'needsLogin' => $needsLogin,
    'sameExecution' => $resolved->result() === $handle->raw(),
    'sameData' => $resolved->data() === $handle->dataOrFail(),
    'sameTrace' => $resolved->result()->trace === $handle->raw()->trace,
    'token' => $resolved->continuationTokenOrFail(),
    'httpCalls' => count($transport->getRecorded()),
];

// HTTP 401 остаётся ошибкой и проходит через стандартное представление.
$failedHandle = $client->records()->get(8)->send();
$failed = $failedHandle->resolved();
if (!$failed instanceof SdkResult) {
    throw new LogicException('Клиент должен использовать SdkResultFactory');
}
$throwsOnFailure = false;
try {
    $failed->recordOrFail();
} catch (SdkException) {
    $throwsOnFailure = true;
}
$observed['failure'] = [
    'isFailed' => $failed->isFailed(),
    'needsLogin' => $failed->requiresReauthorization(),
    'httpStatus' => $failed->errorStatus(),
    'errorCode' => $failed->errorCode(),
    'sameErrors' => $failed->errors() === $failedHandle->raw()->errors,
    'sameExecution' => $failed->result() === $failedHandle->raw(),
    'throwsOnFailure' => $throwsOnFailure,
];

// Фабрика действует на весь клиент; методы для конкретного DTO проверяют его тип.
$saved = $client->send(new SaveRecordRequest($record))->resolved();
if (!$saved instanceof SdkResult) {
    throw new LogicException('Клиент должен использовать SdkResultFactory');
}
$rejectsWrongDto = false;
try {
    $saved->recordOrFail();
} catch (UnexpectedValueException) {
    $rejectsWrongDto = true;
}
$observed['otherOperation'] = [
    'isSuccess' => $saved->isSuccess(),
    'data' => $saved->data(),
    'rejectsWrongDto' => $rejectsWrongDto,
];
$observed['totalHttpCalls'] = count($transport->getRecorded());

echo json_encode($observed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
    . PHP_EOL;
