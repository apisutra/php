<?php

declare(strict_types=1);

use ApiSutra\Attributes\AttributeRegistry;
use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\ResultExceptionConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\Core\AbstractClient;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Exceptions\Auth\AuthRefreshFailedException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResolvedResultFactory;
use ApiSutra\Result\ResultHandle;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Auth\RefreshingAuthenticator;
use ApiSutra\Tests\Stubs\ConstructorOwned\CompositeRequest;
use ApiSutra\Tests\Stubs\Continuation\UndeclaredRequest;
use ApiSutra\Tests\Stubs\Hooks\EarlyReturnHook;
use ApiSutra\Tests\Stubs\Requests\ProtectedRequest;
use ApiSutra\Tests\Stubs\Requests\RefreshTokenRequest;
use ApiSutra\Tests\Stubs\Requests\WrappedPaginatedRequest;
use ApiSutra\Tests\Stubs\ResultContract\ContractDto;
use ApiSutra\Tests\Stubs\ResultContract\DefaultRequest;
use ApiSutra\Tests\Stubs\ResultContract\ProviderFailure;
use ApiSutra\Tests\Stubs\ResultContract\RecordingFactory;
use ApiSutra\Tests\Stubs\ResultContract\ReplaceResult;
use ApiSutra\Tests\Stubs\ResultContract\ReplaceResultHandler;
use ApiSutra\Tests\Stubs\ResultContract\StagedDto;
use ApiSutra\Tests\Stubs\ResultContract\StagedRequest;
use ApiSutra\Tests\Stubs\ResultContract\ValueExtension;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\StrictCache;
use ApiSutra\Timing\ExecutionDeadline;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Pipeline\PipelineContext;

it('проверяет конечный результат stage перед записью в cache', function () {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 5])]);
    $store = new StrictCache();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.example.test',
        cacheConfig: new CacheConfig(store: $store),
        environment: Environment::Testing,
    ), $transport);
    $registry = new AttributeRegistry();
    $registry->register(ReplaceResult::class, ReplaceResultHandler::class);
    $client = new class ($client->getConfig(), $transport, attributes: $registry) extends AbstractClient {
    };
    $first = $client->send(new StagedRequest())->raw();
    $second = $client->send(new StagedRequest())->raw();
    expect($first->errors->first()->context)->toMatchArray(['reason' => 'response_type_mismatch', 'expected' => StagedDto::class])
        ->and($second->isFailed())->toBeTrue()
        ->and($transport->getRecorded())->toHaveCount(2);
});

it('проверяет обработчик cache hit и не обновляет запись при mismatch', function () {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 1])]);
    $store = new StrictCache();
    $config = new ClientConfig(baseUrl: 'https://api.example.test', cacheConfig: new CacheConfig(store: $store), environment: Environment::Testing);
    $good = new TestClient($config, $transport);
    expect($good->send(new DefaultRequest())->dataOrFail())->toBeInstanceOf(ContractDto::class);
    $writes = count($store->keys);
    $bad = new TestClient($config->with(extensions: [new ValueExtension('wrong')]), $transport);
    expect($bad->send(new DefaultRequest())->raw()->errors->first()->context['reason'])->toBe('response_type_mismatch')
        ->and($transport->getRecorded())->toHaveCount(1)
        ->and($store->keys)->toHaveCount($writes);
});

it('проверяет early return без повторной отправки', function (Hook $stage, bool $throw) {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success([])]);
    $factory = new RecordingFactory();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.example.test',
        extensions: [new ValueExtension('wrong')],
        throwOnErrors: $throw,
        resultExceptions: new ResultExceptionConfig(exceptionFactory: $factory),
        environment: Environment::Testing,
    ), $transport);
    $client->hooks()->on($stage, new EarlyReturnHook());
    expect(fn () => $client->send(new DefaultRequest())->dataOrFail())->toThrow(ProviderFailure::class);
    expect($factory->results)->toHaveCount(1)
        ->and($factory->results[0]->errors->first()->context['reason'])->toBe('response_type_mismatch')
        ->and($transport->getRecorded())->toHaveCount(1);
})->with([Hook::AfterResponse, Hook::BeforeHydrate, Hook::AfterHydrate])->with([false, true]);

it('не меняет семантику присваивания context dto из void hook', function () {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 9])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.example.test'), $transport);
    $client->hooks()->on(Hook::AfterHydrate, new class implements HookInterface {
        public function handle(PipelineContext $context): ?array
        {
            $context->dto = new stdClass();
            return null;
        }
    });
    expect($client->send(new DefaultRequest())->dataOrFail())->toBeInstanceOf(ContractDto::class);
});

it('сохраняет nested у null composite и передаёт полную ошибку фабрике', function (bool $throw) {
    $factory = new RecordingFactory();
    $transport = new MockTransport();
    $transport->fake([UndeclaredRequest::class => MockResponse::make('null')]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.example.test',
        throwOnErrors: $throw,
        resultExceptions: new ResultExceptionConfig(exceptionFactory: $factory)
    ), $transport);
    expect(fn () => $client->send((new CompositeRequest())->setClient($client))->dataOrFail())->toThrow(ProviderFailure::class);
    expect($factory->results[0]->nested)->toHaveCount(1)
        ->and($factory->results[0]->nested[0]->isSuccess())->toBeTrue()
        ->and($factory->results[0]->errors->first()->context['reason'])->toBe('response_type_mismatch');
})->with([false, true]);

it('не применяет контракт страницы к агрегированному результату пагинации', function () {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['response' => ['result' => []]])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.example.test'), $transport);
    $result = (new WrappedPaginatedRequest())->setClient($client)->paginate()->pages(1);
    expect($result->isSuccess())->toBeTrue()->and($result->nested)->toHaveCount(1);
});

it('сохраняет PARTIAL и не вызывает фабрику', function () {
    $factory = new RecordingFactory();
    $partial = new ExecutionResult(data: ['partial'], status: ResultStatus::PARTIAL, errors: new ErrorCollection([]), exceptionFactory: $factory);
    $handle = new ResultHandle($partial, new ResolvedResultFactory());
    expect($handle->dataOrFail())->toBe(['partial'])->and($factory->results)->toBeEmpty();
});

it('не превращает ошибки refresh в пользовательские до восстановления основного 401', function (bool $throw) {
    RefreshingAuthenticator::reset();
    $factory = new RecordingFactory();
    $transport = new MockTransport();
    $transport->fake([
        ProtectedRequest::class => MockResponse::make('original', 401),
        RefreshTokenRequest::class => MockResponse::make('refresh failure', 503),
    ]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.example.test',
        auth: new RefreshingAuthenticator(),
        throwOnErrors: $throw,
        resultExceptions: new ResultExceptionConfig(exceptionFactory: $factory),
        retry: new RetryConfig(attempts: 2, baseDelay: 0, retryOn: [], retryExceptions: [Throwable::class]),
    ), $transport);
    expect(fn () => $client->send(new ProtectedRequest())->dataOrFail())->toThrow(ProviderFailure::class);
    expect($factory->results)->toHaveCount(1)
        ->and($factory->results[0]->exception)->toBeInstanceOf(AuthRefreshFailedException::class)
        ->and($factory->results[0]->response->body)->toBe('original')
        ->and($factory->results[0]->exception->dependencyResult->response->status)->toBe(503)
        ->and($factory->results[0]->errors->first()->context['reason'])->toBe('auth_refresh_failed');
    $client->assertSent(ProtectedRequest::class, null, 1);
})->with([false, true]);

it('передаёт фабрике истёкший дедлайн без HTTP', function (bool $throw) {
    $factory = new RecordingFactory();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success([])]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.example.test',
        throwOnErrors: $throw,
        resultExceptions: new ResultExceptionConfig(exceptionFactory: $factory)
    ), $transport);
    expect(fn () => $client->send((new DefaultRequest())->withDeadline(ExecutionDeadline::afterMs(0)))->dataOrFail())->toThrow(ProviderFailure::class);
    expect($factory->results[0]->exception)->toBeInstanceOf(ExecutionDeadlineException::class)
        ->and($factory->results[0]->errors->first()->code)->toBe(ErrorCode::Timeout)
        ->and($transport->getRecorded())->toBeEmpty();
})->with([false, true]);
