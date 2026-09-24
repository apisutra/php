<?php

declare(strict_types=1);

use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Pipeline\Hydration\ResponseDtoHydratorResolver;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\DtoMapping\CallbackHydrator;
use ApiSutra\Tests\Stubs\DtoMapping\FactoryDto;
use ApiSutra\Tests\Stubs\DtoMapping\LocalHydrator;
use ApiSutra\Tests\Stubs\DtoMapping\ReturnsValidationRequest;
use ApiSutra\Tests\Stubs\HydrationRules\RecordDto;
use ApiSutra\Tests\Stubs\ResultContract\ValueExtension;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Tracing\MemoryLogger;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Errors\ValidationError;

it('проверяет все классы декларации до HTTP и hooks с той же ошибкой в позднем resolver', function (?Returns $returns, ?string $responseType, bool $async): void {
    $transport = new MockTransport();
    $logger = new MemoryLogger();
    $config = new ClientConfig(baseUrl: 'https://returns.test', logger: $logger, logLevel: 'debug');
    $client = new TestClient($config, $transport);
    $request = new ReturnsValidationRequest($returns, $responseType);
    $call = $request->setClient($client)->withTraceId('invalid-returns');
    $result = ($async ? $call->sendAsync()->wait() : $call->send())->raw();
    expect($result->errors->first()->code)->toBe(ErrorCode::ConfigurationError)
        ->and($result->response)->toBeNull()->and($result->traceId)->toBe('invalid-returns')
        ->and($request->hookCalled)->toBeFalse()->and($transport->getRecorded())->toBe([]);
    try {
        new ResponseDtoHydratorResolver()->resolve($request, $config);
        $this->fail('Поздний resolver обязан проверять ту же декларацию');
    } catch (ConfigurationException $exception) {
        expect($exception->getMessage())->toBe($result->exception->getMessage());
    }
    $events = array_column(array_column($logger->records, 'context'), 'event');
    expect(array_count_values($events)['failed'])->toBe(1);
})->with([
    'missing response' => [new Returns('Missing\\Response'), null],
    'missing unwrapped type' => [new Returns(RecordDto::class, unwrap: 'data', type: 'Missing\\Unwrapped'), null],
    'unused type is declared' => [new Returns(RecordDto::class, type: 'Missing\\Unused'), null],
    'missing hydrator' => [new Returns(RecordDto::class, hydrator: 'Missing\\Hydrator'), null],
    'wrong interface' => [new Returns(RecordDto::class, hydrator: stdClass::class), null],
    'dynamic response type' => [new Returns(RecordDto::class), 'Missing\\Dynamic'],
    'empty dynamic response' => [null, ''],
])->with([false, true]);

it('отдаёт ошибке декларации приоритет перед отчётом валидации данных', function (
    Returns $returns,
    ErrorCode $expectedCode,
    bool $async,
): void {
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://returns.test'), $transport);
    $request = new ReturnsValidationRequest($returns);
    $error = new ValidationError('value', 'fixture_rule', 'fixture-rejected', null);
    $request->customValidationErrors = [$error];
    $call = $request->setClient($client);
    $result = ($async ? $call->sendAsync()->wait() : $call->send())->raw();
    $validated = $expectedCode === ErrorCode::ValidationFailed;

    expect($result->errors->first()->code)->toBe($expectedCode)
        ->and($result->validationErrors)->toBe($validated ? [$error] : [])
        ->and($request->validationCalls)->toBe($validated ? 1 : 0)
        ->and($request->hookCalled)->toBeFalse()
        ->and($result->response)->toBeNull()
        ->and($transport->getRecorded())->toBe([]);
})->with([
    'valid declaration' => [new Returns(RecordDto::class), ErrorCode::ValidationFailed],
    'missing response' => [new Returns('Missing\\Response'), ErrorCode::ConfigurationError],
    'missing type' => [new Returns(RecordDto::class, type: 'Missing\\Type'), ErrorCode::ConfigurationError],
    'missing hydrator' => [
        new Returns(RecordDto::class, hydrator: 'Missing\\Hydrator'), ErrorCode::ConfigurationError,
    ],
    'wrong interface' => [
        new Returns(RecordDto::class, hydrator: stdClass::class), ErrorCode::ConfigurationError,
    ],
])->with([false, true]);

it('не кеширует вердикт по классу запроса и не требует публичного конструктора DTO', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 7])]);
    $custom = new CallbackHydrator(fn ($data) => FactoryDto::create($data['id']), [FactoryDto::class]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://returns.test', hydration: new HydrationConfig(hydrator: $custom)), $transport);
    $invalid = new Returns(RecordDto::class, hydrator: stdClass::class);
    foreach ([new Returns(FactoryDto::class), $invalid, new Returns(RecordDto::class, hydrator: LocalHydrator::class), $invalid] as $index => $returns) {
        $result = $client->send(new ReturnsValidationRequest($returns))->raw();
        if ($index % 2 === 1) {
            expect($result->errors->first()->code)->toBe(ErrorCode::ConfigurationError);
        } else {
            expect($result->isSuccess())->toBeTrue()->and($result->data->id)->toBe($index === 0 ? 7 : 20);
        }
    }
    expect($transport->getRecorded())->toHaveCount(2);
});

it('не позволяет обходным путям скрыть неверную декларацию', function (string $mode, bool $async): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 7])]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://returns.test',
        extensions: $mode === 'handler' ? [new ValueExtension(new RecordDto(7))] : [],
    ), $transport);
    foreach ([new Returns('Missing\\Response'), new Returns(RecordDto::class, hydrator: 'Missing\\Hydrator')] as $returns) {
        $request = new ReturnsValidationRequest($returns, download: $mode === 'download', earlyReturn: $mode === 'early');
        $call = $request->setClient($client);
        if ($mode === 'raw') {
            $call = $call->withRawResponse();
        }
        $result = ($async ? $call->sendAsync()->wait() : $call->send())->raw();
        expect($result->errors->first()->code)->toBe(ErrorCode::ConfigurationError)
            ->and($request->hookCalled)->toBeFalse();
    }
    expect($transport->getRecorded())->toBe([]);
})->with(['download', 'handler', 'early', 'raw'])->with([false, true]);

it('не разрешает зависимости гидратора до того как понадобилась гидратация', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 7])]);
    $returns = new Returns(RecordDto::class, hydrator: CallbackHydrator::class);
    $ready = new RecordDto(42);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://returns.test', extensions: [new ValueExtension($ready)]), $transport);
    expect($client->send(new ReturnsValidationRequest($returns))->dataOrFail())->toBe($ready);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://returns.test'), $transport);
    $result = $client->send(new ReturnsValidationRequest($returns))->raw();
    expect($result->errors->first()->code)->toBe(ErrorCode::ConfigurationError)
        ->and($result->response->status)->toBe(200)->and($transport->getRecorded())->toHaveCount(2);
});
