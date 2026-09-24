<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\ResultExceptionConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResultMeta;
use ApiSutra\Contracts\Interfaces\Errors\ExecutionExceptionFactoryInterface;
use ApiSutra\Core\AbstractClient;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Exceptions\Configuration\ExceptionFactoryException;
use ApiSutra\Exceptions\ControlFlow\RetryableException;
use ApiSutra\Exceptions\Request\UnauthorizedException;
use ApiSutra\Exceptions\Serialization\ResponseTypeMismatchException;
use ApiSutra\Exceptions\Transport\ConnectionException;
use ApiSutra\Request\RequestSpecResolver;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResultHandle;
use ApiSutra\Contracts\Interfaces\Execution\ResultPromiseInterface;
use ApiSutra\Result\ResultMetaExtractorInterface;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Testing\MockSequence;
use ApiSutra\Tests\Stubs\Requests\PlainRequest;
use ApiSutra\Tests\Stubs\ResultContract\ContractDto;
use ApiSutra\Tests\Stubs\ResultContract\DefaultRequest;
use ApiSutra\Tests\Stubs\ResultContract\EmptyMessageRequest;
use ApiSutra\Tests\Stubs\ResultContract\LocalMessageRequest;
use ApiSutra\Tests\Stubs\ResultContract\ProviderFailure;
use ApiSutra\Tests\Stubs\ResultContract\RecordingFactory;
use ApiSutra\Tests\Stubs\ResultContract\SecondMessageRequest;
use ApiSutra\Tests\Stubs\ResultContract\SpecializedDto;
use ApiSutra\Tests\Stubs\ResultContract\UnwrappedRequest;
use ApiSutra\Tests\Stubs\ResultContract\ValueExtension;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

/** @return array{TestClient, MockTransport} */
function apisutraResultContractClient(mixed $value = 'unexpected', array $options = [], MockResponse|MockSequence|null $response = null): array
{
    $transport = new MockTransport();
    $transport->fake(['*' => $response ?? MockResponse::success(['id' => 1])]);
    $config = new ClientConfig(
        baseUrl: 'https://api.example.test',
        environment: Environment::Testing,
        extensions: [new ValueExtension($value)],
    )->with(...$options);
    return [new TestClient($config, $transport), $transport];
}

it('отклоняет чужое значение handler до объявления успеха', function (mixed $value) {
    [$client, $transport] = apisutraResultContractClient($value);
    $result = $client->send(new DefaultRequest())->raw();
    expect($result->status)->toBe(ResultStatus::FAILED)
        ->and($result->errors->first()->code)->toBe(ErrorCode::HydrationError)
        ->and($result->errors->first()->context)->toMatchArray([
            'reason' => 'response_type_mismatch',
            'expected' => ContractDto::class,
            'actual' => get_debug_type($value),
            'path' => '$',
        ])
        ->and($result->exception)->toBeInstanceOf(ResponseTypeMismatchException::class)
        ->and($result->response?->status)->toBe(200)
        ->and($result->data)->toBeNull()
        ->and($transport->getRecorded())->toHaveCount(1);
})->with(['строка' => ['secret-content'], 'массив' => [['secret' => 'content']], 'объект' => [new stdClass()], 'false' => [false]]);

it('принимает готовый DTO и подкласс без повторной гидратации', function (ContractDto $dto) {
    [$client] = apisutraResultContractClient($dto);
    expect($client->send(new DefaultRequest())->dataOrFail())->toBe($dto);
})->with([new ContractDto(5), new SpecializedDto(6)]);

it('сохраняет fallback null handler и effective type после unwrap', function () {
    [$client] = apisutraResultContractClient(null, response: MockResponse::success(['data' => ['id' => 8]]));
    expect($client->send(new UnwrappedRequest())->dataOrFail())->toBeInstanceOf(ContractDto::class);
    [$client] = apisutraResultContractClient(new ContractDto());
    expect($client->send(new UnwrappedRequest())->dataOrFail())->toBeInstanceOf(ContractDto::class);
});

it('выбирает сообщения операции, клиента и встроенный default', function () {
    [$client] = apisutraResultContractClient(options: ['resultExceptions' => new ResultExceptionConfig(mismatchMessage: 'Общее сообщение.')]);
    foreach ([new DefaultRequest(), new LocalMessageRequest(), new SecondMessageRequest()] as $i => $request) {
        $result = $client->send($request)->raw();
        $message = ['Общее сообщение.', 'Локальное сообщение операции.', 'Сообщение другой операции.'][$i];
        expect($result->exception->getMessage())->toBe($message)
            ->and($result->errors->first()->message)->toBe($message);
    }
    [$client] = apisutraResultContractClient('secret-content');
    expect($client->send(new DefaultRequest())->raw()->exception->getMessage())
        ->toContain(DefaultRequest::class, ContractDto::class, 'string')->not->toContain('secret-content');
});

it('не валидирует текст при inventory и отклоняет пустое сообщение до HTTP', function (bool $global) {
    expect((new RequestSpecResolver())->resolveClass(EmptyMessageRequest::class)->returns->mismatchMessage)->toBe('   ');
    [$client, $transport] = apisutraResultContractClient(options: [
        'resultExceptions' => $global ? new ResultExceptionConfig(mismatchMessage: '') : null,
    ]);
    $result = $client->send($global ? new DefaultRequest() : new EmptyMessageRequest())->raw();
    expect($result->errors->first()->code)->toBe(ErrorCode::ConfigurationError)
        ->and($transport->getRecorded())->toBeEmpty();
})->with([true, false]);

it('не вызывает фабрику при чтении raw/resolved и сохраняет исходную ошибку', function () {
    $factory = new RecordingFactory();
    [$client, $transport] = apisutraResultContractClient(options: ['resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory)]);
    $handle = $client->send(new LocalMessageRequest());
    $raw = $handle->raw();
    $handle->resolved()->result();
    expect($factory->results)->toBeEmpty();
    try {
        $handle->dataOrFail();
        test()->fail('Ожидалась ошибка');
    } catch (ProviderFailure $failure) {
        expect($failure->getMessage())->toBe('Локальное сообщение операции.')
            ->and($failure->getPrevious())->toBe($raw->exception);
    }
    expect($handle->raw())->toBe($raw)
        ->and($factory->results)->toBe([$raw])
        ->and($transport->getRecorded())->toHaveCount(1);
});

it('выдаёт собственное исключение во всех публичных режимах', function (bool $async, bool $throw, string $accessor) {
    $factory = new RecordingFactory();
    [$client, $transport] = apisutraResultContractClient(options: [
        'throwOnErrors' => $throw,
        'resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory),
    ]);
    expect(function () use ($client, $async, $accessor) {
        $handle = ($async ? $client->sendAsync(new LocalMessageRequest())->wait() : $client->send(new LocalMessageRequest()));
        if ($accessor === 'data') {
            $handle->dataOrFail();
        } else {
            $handle->raw()->throw();
        }
    })->toThrow(ProviderFailure::class, 'Локальное сообщение операции.');
    expect($factory->results)->toHaveCount(1)
        ->and($factory->results[0]->exception)->toBeInstanceOf(ResponseTypeMismatchException::class)
        ->and($factory->results[0]->response?->status)->toBe(200)
        ->and($transport->getRecorded())->toHaveCount(1);
})->with([false, true])->with([false, true])->with(['data', 'throw']);

it('не применяет mismatch сообщение к HTTP или ошибке поля DTO', function (bool $http) {
    $factory = new RecordingFactory();
    [$client] = apisutraResultContractClient(
        null,
        ['resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory)],
        $http ? MockResponse::serverError() : MockResponse::success(['id' => ['wrong']])
    );
    expect(fn () => $client->send(new LocalMessageRequest())->dataOrFail())->toThrow(ProviderFailure::class);
    expect($factory->messages[0])->not->toBe('Локальное сообщение операции.')
        ->and($factory->results[0]->errors->first()->code)->toBe($http ? ErrorCode::ServerError : ErrorCode::HydrationError);
})->with([true, false]);

it('fallback фабрики сохраняет объект стандартного исключения', function () {
    $factory = new RecordingFactory(fallback: true);
    [$client] = apisutraResultContractClient(options: ['resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory)]);
    $result = $client->send(new DefaultRequest())->raw();
    try {
        $result->throw();
        test()->fail('Ожидалась ошибка');
    } catch (ResponseTypeMismatchException $failure) {
        expect($failure)->toBe($result->exception);
    }
});

it('сохраняет первичный результат при ошибке фабрики без нового HTTP', function (bool $throw) {
    $cause = new RuntimeException('Ошибка приложения');
    $factory = new RecordingFactory(failure: $cause);
    [$client, $transport] = apisutraResultContractClient(options: [
        'throwOnErrors' => $throw,
        'resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory),
    ]);
    try {
        $client->send(new DefaultRequest())->dataOrFail();
        test()->fail('Ожидалась ошибка');
    } catch (ExceptionFactoryException $failure) {
        expect($failure->reason)->toBe('exception_factory_failed')
            ->and($failure->getPrevious())->toBe($cause)
            ->and($failure->result->exception)->toBeInstanceOf(ResponseTypeMismatchException::class);
    }
    expect($factory->results)->toHaveCount(1)->and($transport->getRecorded())->toHaveCount(1);
})->with([false, true]);

it('не повторяет запрос из-за класса пользовательской ошибки', function (Throwable $returned) {
    $factory = new RecordingFactory(returned: $returned);
    [$client, $transport] = apisutraResultContractClient(options: [
        'throwOnErrors' => true,
        'retry' => new RetryConfig(attempts: 3),
        'resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory),
    ]);
    expect(fn () => $client->send(new DefaultRequest()))->toThrow($returned);
    expect($factory->results)->toHaveCount(1)->and($transport->getRecorded())->toHaveCount(1);
})->with([
    new ConnectionException('Подставное транспортное исключение'),
    new RetryableException('Подставное требование повтора'),
    new UnauthorizedException('Подставное требование авторизации', null),
]);

it('сохраняет канонические ошибки в parallel batch и pool при throwOnErrors', function (string $kind) {
    $factory = new RecordingFactory();
    [$client] = apisutraResultContractClient(options: [
        'throwOnErrors' => true,
        'resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory),
    ]);
    $requests = [new DefaultRequest(), new LocalMessageRequest()];
    expect(fn () => $kind === 'pool'
        ? $client->pool($requests)->send()
        : $client->batch($requests)->parallel()->withFailStrategy(FailStrategy::Partial)->send())->toThrow(ProviderFailure::class);
    expect($factory->results)->toHaveCount(1);
    $result = $factory->results[0];
    foreach ($result->nested as $item) {
        expect($item->errors->first()->code)->toBe(ErrorCode::HydrationError)
            ->and($item->errors->first()->context['reason'])->toBe('response_type_mismatch')
            ->and($item->response?->status)->toBe(200);
        expect(fn () => $item->throw())->toThrow(ProviderFailure::class);
    }
    expect(fn () => $result->throw())->toThrow(ProviderFailure::class);
})->with(['batch', 'pool']);

it('не теряет общую политику при копировании ClientConfig', function () {
    $factory = new RecordingFactory();
    $block = new ResultExceptionConfig(mismatchMessage: 'Общий текст', exceptionFactory: $factory);
    $config = new ClientConfig(baseUrl: 'https://api.example.test', resultExceptions: $block);
    expect($config->with()->resultExceptions)->toBe($block)
        ->and($config->with(timeout: 5)->resultExceptions)->toBe($block)
        ->and($config->with(resultExceptions: null)->resultExceptions)->toBeNull();
});

it('сохраняет изоляцию фабрик клиентов при позднем чтении результата', function () {
    $first = new RecordingFactory();
    $second = new RecordingFactory();
    [$a] = apisutraResultContractClient(options: ['resultExceptions' => new ResultExceptionConfig(exceptionFactory: $first)]);
    [$b] = apisutraResultContractClient(options: ['resultExceptions' => new ResultExceptionConfig(exceptionFactory: $second)]);
    $request = new DefaultRequest();
    $ha = $a->send($request);
    $hb = $b->send($request);
    expect(fn () => $ha->dataOrFail())->toThrow(ProviderFailure::class);
    expect($first->results)->toHaveCount(1)->and($second->results)->toBeEmpty();
    expect(fn () => $hb->dataOrFail())->toThrow(ProviderFailure::class);
    expect($second->results)->toHaveCount(1);
});

it('не ограничивает тип ответа без Returns', function (mixed $value) {
    [$client] = apisutraResultContractClient($value);
    expect($client->send(new PlainRequest('q'))->dataOrFail())->toBe($value);
})->with(['текст', false, ['value' => 1]]);

it('сохраняет фабрику и извлечённую meta при всех способах выдачи ошибки', function (bool $async, bool $throw) {
    $meta = new class implements ResultMeta {
    };
    $extractor = new class ($meta) implements ResultMetaExtractorInterface {
        public function __construct(private ResultMeta $meta)
        {
        }
        public function extract(ExecutionResult $result): ?ResultMeta
        {
            return $this->meta;
        }
    };
    $factory = new RecordingFactory();
    [$client, $transport] = apisutraResultContractClient(options: [
        'throwOnErrors' => $throw,
        'resultMetaExtractor' => $extractor,
        'resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory),
    ]);
    expect(fn () => ($async ? $client->sendAsync(new DefaultRequest())->wait() : $client->send(new DefaultRequest()))->dataOrFail())->toThrow(ProviderFailure::class);
    expect($factory->results)->toHaveCount(1)
        ->and($factory->results[0]->meta)->toBe($meta)
        ->and($factory->results[0]->response?->status)->toBe(200)
        ->and($transport->getRecorded())->toHaveCount(1);
})->with([[false, false], [false, true], [true, false], [true, true]]);

it('изолирует TypeError неверной реализации фабрики от исходной ошибки', function () {
    $factory = new class implements ExecutionExceptionFactoryInterface {
        public function make(ExecutionResult $result, string $message): ?Throwable
        {
            return [];
        }
    };
    [$client, $transport] = apisutraResultContractClient(options: [
        'resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory),
    ]);
    $raw = $client->send(new DefaultRequest())->raw();
    try {
        $raw->throw();
        test()->fail('Ожидался отказ фабрики');
    } catch (ExceptionFactoryException $error) {
        expect($error->result)->toBe($raw)->and($error->getPrevious())->toBeInstanceOf(TypeError::class);
    }
    expect($raw->errors->first()->context['reason'])->toBe('response_type_mismatch')
        ->and($transport->getRecorded())->toHaveCount(1);
});

it('вызывает фабрику после завершения HTTP retry и только для конечной ошибки', function () {
    $factory = new RecordingFactory();
    [$client, $transport] = apisutraResultContractClient(options: [
        'throwOnErrors' => true,
        'retry' => new RetryConfig(attempts: 3, baseDelay: 0, jitter: false),
        'resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory),
    ], response: MockResponse::sequence([
        MockResponse::make(['error' => 'temporary'], 503),
        MockResponse::make(['error' => 'denied'], 403),
    ]));
    expect(fn () => $client->send(new DefaultRequest()))->toThrow(ProviderFailure::class);
    expect($factory->results)->toHaveCount(1)
        ->and($factory->results[0]->response?->status)->toBe(403)
        ->and($transport->getRecorded())->toHaveCount(2);
});

it('отдаёт callback pool собственное исключение с каноническим nested', function () {
    $factory = new RecordingFactory();
    [$client] = apisutraResultContractClient(options: [
        'throwOnErrors' => true,
        'resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory),
    ]);
    $seen = null;
    expect(function () use ($client, &$seen) { return $client->pool([new DefaultRequest()])->withExceptionHandler(
        function (Throwable $error) use (&$seen): void {
            $seen = $error;
        },
    )->send(); })->toThrow(ProviderFailure::class);
    $result = $factory->results[1];
    expect($seen)->toBeInstanceOf(ProviderFailure::class)
        ->and($result->nested[0]->exception)->toBeInstanceOf(ResponseTypeMismatchException::class)
        ->and($factory->results)->toHaveCount(2);

});

it('внутренние batch и pool не используют публичный override отправки', function (bool $pool) {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 1])]);
    $client = new class (new ClientConfig(baseUrl: 'https://api.example.test'), $transport) extends AbstractClient {
        public int $calls = 0;

        public function send(RequestInterface $request): ResultHandle
        {
            $this->calls++;
            return parent::send($request);
        }

        public function sendAsync(RequestInterface $request): ResultPromiseInterface
        {
            $this->calls++;
            return parent::sendAsync($request);
        }
    };
    $result = $pool ? $client->pool([new DefaultRequest()])->send() : $client->batch([new DefaultRequest()])->send();
    expect($result->isSuccess())->toBeTrue()->and($client->calls)->toBe(0);
})->with([false, true]);

it('не читает и не закрывает поток для диагностики несовпадения типа', function () {
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, 'private-stream-content');
    fseek($stream, 3);
    try {
        [$client, $transport] = apisutraResultContractClient($stream);
        $raw = $client->send(new DefaultRequest())->raw();
        expect($raw->errors->first()->context['reason'])->toBe('response_type_mismatch')
            ->and($raw->errors->first()->context['actual'])->toBe('resource (stream)')
            ->and($raw->errors->first()->message)->not->toContain('private-stream-content')
            ->and(is_resource($stream))->toBeTrue()
            ->and(ftell($stream))->toBe(3)
            ->and($transport->getRecorded())->toHaveCount(1);
    } finally {
        fclose($stream);
    }
});

it('сохраняет configuration_error для недоступного объявленного DTO', function (mixed $value) {
    $request = new class extends AbstractRequest {
        public function getResponseType(): ?string
        {
            return 'MissingResultContractDto';
        }
    };
    [$client] = apisutraResultContractClient($value);
    $raw = $client->send($request)->raw();
    expect($raw->errors->first()->code)->toBe(ErrorCode::ConfigurationError)
        ->and($raw->exception)->not->toBeInstanceOf(ResponseTypeMismatchException::class);
})->with([null, 'wrong']);
