<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Config\ResultExceptionConfig;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Exceptions\Continuation\ContinuationAwaitException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HandlerSpec;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Serialization\Rules\SourcePathKind;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\AttributeHydration as Attributed;
use ApiSutra\Tests\Stubs\ConstructorOwned\State;
use ApiSutra\Tests\Stubs\ConstructorOwned\ValueHandler;
use ApiSutra\Tests\Stubs\HydrationRules\BodyRootRequest;
use ApiSutra\Tests\Stubs\HydrationRules\OwnerDto;
use ApiSutra\Tests\Stubs\ResultContract\AttributedRequest;
use ApiSutra\Tests\Stubs\ResultContract\ExternalRecordRequest;
use ApiSutra\Tests\Stubs\ResultContract\ProviderFailure;
use ApiSutra\Tests\Stubs\ResultContract\RecordingFactory;
use ApiSutra\Tests\Stubs\ResultContract\ValueExtension;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

/**
 * @param array<string, mixed> $payload
 * @param array<string, mixed> $options
 * @return array{TestClient, MockTransport}
 */
function resultHydrationClient(array $payload, array $options = []): array
{
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake(['*' => MockResponse::success($payload)]);
    $config = new ClientConfig(...['baseUrl' => 'https://results.test', ...$options]);
    return [new TestClient($config, $transport), $transport];
}

it('сохраняет независимые блоки гидратации и исключений в копиях', function (
    bool $withHydration,
    bool $withExceptions,
): void {
    $hydration = $withHydration ? new HydrationConfig(policy: new RulePolicy(scalars: ScalarPolicy::Strict)) : null;
    $exceptions = $withExceptions ? new ResultExceptionConfig(exceptionFactory: new RecordingFactory()) : null;
    $configs = [
        new ClientConfig(baseUrl: 'https://results.test', hydration: $hydration, resultExceptions: $exceptions),
    ];
    foreach ($configs as $config) {
        foreach ([$config->with(), $config->with(timeout: 3)] as $copy) {
            expect($copy->hydration)->toBe($hydration)->and($copy->resultExceptions)->toBe($exceptions);
        }
        expect($config->with(hydration: null)->hydration)->toBeNull()
            ->and($config->with(hydration: null)->resultExceptions)->toBe($exceptions)
            ->and($config->with(resultExceptions: null)->hydration)->toBe($hydration)
            ->and($config->with(resultExceptions: null)->resultExceptions)->toBeNull()
            ->and($config->hydration)->toBe($hydration)->and($config->resultExceptions)->toBe($exceptions);
    }
})->with([false, true])->with([false, true]);

it('сохраняет исходную ошибку атрибута с фабрикой и локальным текстом Returns', function (
    array $payload,
    string $reason,
    string $source,
    bool $async,
    bool $throw,
): void {
    [$plain] = resultHydrationClient(['data' => $payload]);
    $original = ($async ? $plain->sendAsync(new AttributedRequest())->wait() : $plain->send(new AttributedRequest()))->raw();
    expect($original->exception)->toBeInstanceOf(HydrationException::class)
        ->and($original->exception->reason)->toBe($reason)
        ->and($original->exception->sourcePath)->toBe($source);

    $factory = new RecordingFactory();
    [$client, $transport] = resultHydrationClient(['data' => $payload], [
        'throwOnErrors' => $throw,
        'resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory),
    ]);
    expect(fn () => ($async ? $client->sendAsync(new AttributedRequest())->wait() : $client->send(new AttributedRequest()))->dataOrFail())->toThrow(ProviderFailure::class);
    expect($factory->results)->toHaveCount(1);
    $result = $factory->results[0];
    expect($result->errors->first()->code)->toBe(ErrorCode::HydrationError)
        ->and($result->exception->context())->toBe($original->exception->context())
        ->and($result->errors->first()->context)->toMatchArray($original->exception->context())
        ->and($result->response?->body)->toBe($original->response?->body)
        ->and($result->response?->status)->toBe(200)
        ->and($factory->messages)->toBe([$original->exception->getMessage()])
        ->and($factory->messages[0])->not->toBe('Операция вернула неожиданный тип записи.')
        ->and($transport->getRecorded())->toHaveCount(1);
})->with([
    'required' => [['kind' => 'record'], 'required_field_missing', '/data/record_id'],
    'null' => [['record_id' => 7, 'kind' => 'record', 'stock' => null], 'explicit_null_not_allowed', '/data/stock'],
    'list' => [['record_id' => 7, 'kind' => 'record', 'rows' => ['key' => [1]]], 'invalid_list_shape', '/data/rows'],
    'item' => [['record_id' => 7, 'kind' => 'record', 'rows' => [[1, 'bad']]], 'invalid_field_type', '/data/rows/0/1'],
    'constructor' => [['record_id' => 7, 'kind' => 'other'], 'constructor_value_mismatch', '/data/kind'],
])->with([false, true])->with([false, true]);

it('принимает атрибутный DTO и не повторяет casts при чтении результата', function (): void {
    State::$handlers = 0;
    $factory = new RecordingFactory();
    $payload = ['data' => ['record_id' => 7, 'kind' => 'record', 'future' => false]];
    [$client, $transport] = resultHydrationClient($payload, [
        'hydration' => new HydrationConfig(policy: new RulePolicy(
            casts: ['int' => new HandlerSpec(ValueHandler::class)],
        )),
        'resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory),
    ]);
    try {
        $handle = $client->send(new AttributedRequest());
        $dto = $handle->dataOrFail();
        expect($dto)->toBeInstanceOf(Attributed\Record::class)
            ->and($dto->id)->toBe(7)->and($dto->kind)->toBe('record')->and($dto->extra)->toBe(['future' => false])
            ->and($handle->dataOrFail())->toBe($dto)->and(State::$handlers)->toBe(1)
            ->and($factory->results)->toBeEmpty()->and($transport->getRecorded())->toHaveCount(1);
    } finally {
        State::$handlers = 0;
    }
});

it('проверяет готовый атрибутный DTO handler без повторной гидратации полей', function (bool $valid): void {
    $factory = new RecordingFactory();
    $value = $valid ? new Attributed\Record(7) : 'wrong';
    [$client, $transport] = resultHydrationClient([], [
        'extensions' => [new ValueExtension($value)],
        'resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory),
    ]);
    $handle = $client->send(new AttributedRequest());
    if ($valid) {
        expect($handle->dataOrFail())->toBe($value)->and($factory->results)->toBeEmpty();
    } else {
        expect(fn () => $handle->dataOrFail())
            ->toThrow(ProviderFailure::class, 'Операция вернула неожиданный тип записи.');
        expect($factory->results[0]->errors->first()->context['reason'])->toBe('response_type_mismatch');
    }
    expect($transport->getRecorded())->toHaveCount(1);
})->with([false, true]);

it('сохраняет discovery receiver до гидратации и изоляцию внешних правил при фабрике', function (): void {
    $factory = new RecordingFactory();
    $rules = HydrationRules::create()->withDto(OwnerDto::class, DtoRules::create()
        ->field('id', FieldRule::create()->from('record_id'))->extras('extra'));
    [$client, $transport] = resultHydrationClient(['record_id' => 7, 'future' => false], [
        'hydration' => new HydrationConfig(rules: $rules),
        'resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory),
    ]);
    $other = new TestClient($client->getConfig()->with(hydration: null), $transport);
    $manual = new Attributed\DiscoveryDto(7, ['secret' => 'synthetic']);
    $client->send(new BodyRootRequest($manual))->dataOrFail();
    expect(json_decode($transport->getRecorded()[0]->body, true))->toBe(['id' => 7]);
    $dto = $client->send(new ExternalRecordRequest())->dataOrFail();
    expect($dto->id)->toBe(7)->and($dto->extra)->toBe(['future' => false]);
    $client->send(new BodyRootRequest($dto))->dataOrFail();
    $other->send(new BodyRootRequest($dto))->dataOrFail();
    expect(json_decode($transport->getRecorded()[2]->body, true))->toBe(['id' => 7])
        ->and(json_decode($transport->getRecorded()[3]->body, true))->toHaveKey('extra')
        ->and($factory->results)->toBeEmpty();
});

it('сохраняет границу источника у composite и hook при фабрике', function (bool $composite, bool $throw): void {
    $factory = new RecordingFactory();
    [$client, $transport] = resultHydrationClient($composite ? [] : ['envelope' => []], [
        'throwOnErrors' => $throw,
        'resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory),
    ]);
    $request = $composite ? new Attributed\CompositeRequest() : new Attributed\HookedReportRequest();
    $request->setClient($client);
    expect(fn () => $client->send($request)->dataOrFail())->toThrow(ProviderFailure::class);
    expect($factory->results)->toHaveCount(1)
        ->and($factory->results[0]->exception->reason)->toBe('required_field_missing')
        ->and($factory->results[0]->exception->sourcePathKind)->toBe(SourcePathKind::Boundary)
        ->and($transport->getRecorded())->toHaveCount(1);
})->with([false, true])->with([false, true]);

it('сохраняет контейнер и явное включение typed items-only при фабрике', function (bool $typed): void {
    $factory = new RecordingFactory();
    [$client] = resultHydrationClient(['response' => ['rows' => [['id' => 7, 'future' => false]]]], [
        'hydration' => $typed ? new HydrationConfig() : null,
        'resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory),
    ]);
    $item = $client->send(new Attributed\ItemsRequest())->dataOrFail()[0];
    if ($typed) {
        expect($item)->toBeInstanceOf(Attributed\Row::class)->and($item->_extra)->toBe(['future' => false]);
    } else {
        expect($item)->toBe(['id' => 7, 'future' => false]);
    }
    $result = (new Attributed\ContainerRequest())->setClient($client)->paginate()->pages(1);
    expect($result->isSuccess())->toBeTrue()->and($result->nested)->toHaveCount(1)
        ->and($result->nested[0]->data->items()[0])->toBeInstanceOf(Attributed\Row::class)
        ->and($factory->results)->toBeEmpty();
})->with([false, true]);

it('сохраняет ошибку атрибутного Ready внутри continuation', function (): void {
    $factory = new RecordingFactory();
    [$client, $transport] = resultHydrationClient(['data' => []], [
        'resultExceptions' => new ResultExceptionConfig(exceptionFactory: $factory),
    ]);
    try {
        $client->send(new Attributed\AwaitRequest())->await();
        test()->fail('Ожидалась ошибка Ready');
    } catch (ContinuationAwaitException $error) {
        expect($error->reason)->toBe('final_hydration_failed')
            ->and($error->getPrevious())->toBeInstanceOf(HydrationException::class)
            ->and($error->getPrevious()->reason)->toBe('required_field_missing')
            ->and($error->getPrevious()->sourcePath)->toBe('/data/id');
    }
    expect($factory->results)->toBeEmpty()->and($transport->getRecorded())->toHaveCount(1);
});
