<?php

declare(strict_types=1);

use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Result\PoolSummary;
use ApiSutra\Result\ResultHandle;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use Example\Records\Config\ClientConfigFactory;
use Example\Records\DemoClient;
use Example\Records\Resources\Records\Get\GetRecordRequest;
use GuzzleHttp\Promise\Utils;

require __DIR__ . '/../sdk/bootstrap.php';

$payload = json_decode((string) file_get_contents(__DIR__ . '/../sdk/fixtures/record.json'), true, flags: JSON_THROW_ON_ERROR);
$transport = new MockTransport();
$transport->preventStrayRequests();
$transport->fake([
    GetRecordRequest::class => static fn (GetRecordRequest $request): MockResponse => $request->id === 404
        ? MockResponse::notFound() : MockResponse::success($payload),
]);
$config = ClientConfigFactory::create();
$client = new DemoClient($config, $transport);
$requests = static fn (): array => [new GetRecordRequest(7), new GetRecordRequest(7)];

// Копия builder получает свои настройки; для каждого запуска создаём новый источник.
$pool = $client->pool($requests(), concurrency: 1);
$parallelPool = $pool->withConcurrency(2);
$single = $client->sendAsync(new GetRecordRequest(7));
[$handle, $poolResult, $summary, $batchResult] = Utils::all([
    $single,
    $parallelPool->sendAsync(),
    $client->pool($requests())->withConcurrency(2)->consumeAsync(),
    $client->batch($requests())->parallel()->withConcurrency(2)->withFailStrategy(FailStrategy::Partial)->sendAsync(),
])->wait();

// Промис из then разворачивается: следующий callback получает PoolSummary.
$chainTotal = $single
    ->then(fn (ResultHandle $ready) => $client->pool($requests())->consumeAsync())
    ->then(static fn (PoolSummary $completed): int => $completed->total)
    ->wait();

// По умолчанию HTTP-ошибка даёт готовый FAILED handle: otherwise не вызывается.
$otherwiseCalled = false;
$failed = $client->sendAsync(new GetRecordRequest(404));
$failedHandle = $failed->otherwise(function (Throwable $error) use (&$otherwiseCalled): never {
    $otherwiseCalled = true;
    throw $error;
})->wait();

// dataOrFail внутри then превращает FAILED в reject; fallback выбран приложением явно.
$recovered = $failed
    ->then(static fn (ResultHandle $ready) => $ready->dataOrFail())
    ->otherwise(static fn (Throwable $error): string => 'fallback')
    ->wait();

// При throwOnErrors=true источник сразу отклоняется, без вызова dataOrFail.
$throwingClient = new DemoClient($config->with(throwOnErrors: true), $transport);
$throwingRecovery = $throwingClient->sendAsync(new GetRecordRequest(404))
    ->otherwise(static fn (Throwable $error): string => 'fallback')
    ->wait();

$output = [
    'handle' => $handle instanceof ResultHandle,
    'same_handle' => $single->wait() === $handle,
    'wait_false' => $single->wait(false),
    'pool_total' => $poolResult->results()->summarize()->total,
    'consume_total' => $summary->total,
    'batch_total' => $batchResult->results()->summarize()->total,
    'chain_total' => $chainTotal,
    'fulfilled_failed' => $failedHandle->raw()->isFailed(),
    'otherwise_on_failed' => $otherwiseCalled,
    'recovered' => $recovered,
    'throwing_recovery' => $throwingRecovery,
];

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    echo json_encode($output, JSON_THROW_ON_ERROR) . PHP_EOL;
}
return $output;
