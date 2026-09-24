<?php

declare(strict_types=1);

use ApiSutra\Config\HydrationConfig;
use ApiSutra\Tests\Stubs\HydrationRules\BodyRootRequest;
use ApiSutra\Tests\Stubs\ConstructorOwned\InheritedDto;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Continuation\ContinuationAwaitOptions;
use ApiSutra\Exceptions\Continuation\ContinuationAwaitException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\SourcePathKind;
use ApiSutra\Serialization\Rules\ValueShape;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\ConstructorOwned\ArrayDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\ArrayRequest;
use ApiSutra\Tests\Stubs\ConstructorOwned\AwaitRequest;
use ApiSutra\Tests\Stubs\ConstructorOwned\CompositeRequest;
use ApiSutra\Tests\Stubs\ConstructorOwned\GraphDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\ItemsRequest;
use ApiSutra\Tests\Stubs\ConstructorOwned\NodeDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\NodeRequest;
use ApiSutra\Tests\Stubs\ConstructorOwned\State;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\ConstructorOwnedFixture;
use ApiSutra\Tests\Support\HydrationRulesFixture;
use ApiSutra\Tests\Support\StrictCache;
use ApiSutra\Transport\MockTransport;

function ownedNodeRules(): HydrationRules
{
    return HydrationRules::create()->withDto(NodeDto::class, DtoRules::create()
        ->field('value', FieldRule::create()->from('kind', 'legacy')->constructorValue())
        ->extras('_extra'));
}

/** @return array{TestClient, MockTransport} */
function ownedClient(array $payload, ?HydrationRules $rules = null): array
{
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake(['*' => MockResponse::success($payload)]);
    $config = new ClientConfig(
        baseUrl: 'https://owned.test',
        hydration: new HydrationConfig(rules: $rules ?? ownedNodeRules()),
        cacheConfig: new CacheConfig(store: new StrictCache())
    );
    return [new TestClient($config, $transport), $transport];
}

beforeEach(function (): void {
    State::$calls = 0;
    State::$value = 'known';
});

it('применяет constructorValue через Returns/promise/response cache и сохраняет результат ошибки', function (bool $async, bool $valid): void {
    [$client, $transport] = ownedClient(['data' => ['id' => 7, 'kind' => $valid ? 'known' : 'secret-value', 'legacy' => 'unused']]);
    for ($i = 0; $i < 2; $i++) {
        $handle = ($async ? $client->sendAsync(new NodeRequest())->wait() : $client->send(new NodeRequest()));
        if ($valid) {
            $dto = $handle->dataOrFail();
            expect($dto)->toBeInstanceOf(NodeDto::class)->and($dto->id)->toBe(7)
                ->and($dto->_extra)->toBe(['legacy' => 'unused']);
        } else {
            $result = $handle->raw();
            expect($result->exception)->toBeInstanceOf(HydrationException::class)
                ->and($result->exception->reason)->toBe('constructor_value_mismatch')
                ->and($result->exception->path)->toBe('data.value')->and($result->exception->sourcePath)->toBe('/data/kind')
                ->and($result->response->status)->toBe(200)->and($result->errors->first()->code->value)->toBe('hydration_error');
            expect(fn () => $handle->dataOrFail())->toThrow(HydrationException::class);
        }
    }
    expect(State::$calls)->toBe(2)->and($transport->getRecorded())->toHaveCount($valid ? 1 : 2);
})->with([false, true])->with([true, false]);

it('сохраняет сравнение в pagination, composite и Ready await', function (string $entry, bool $valid): void {
    $node = ['id' => 7, 'kind' => $valid ? 'known' : 'other'];
    $payload = match ($entry) {
        'pagination' => ['response' => ['rows' => [$node]]], 'await' => ['data' => $node], default => $node,
    };
    [$client, $transport] = ownedClient($payload);
    if ($entry === 'await') {
        $handle = $client->send(new AwaitRequest());
        if ($valid) {
            expect($handle->await()->value)->toBe('known');
            $dto = $handle->awaitAs(NodeDto::class);
            expect($dto->value)->toBe('known')->and($handle->awaitAs(NodeDto::class))->toBe($dto);
        } else {
            try {
                $handle->await(new ContinuationAwaitOptions(3, 0));
                throw new LogicException('Ожидалась ошибка Ready');
            } catch (ContinuationAwaitException $error) {
                expect($error->reason)->toBe('final_hydration_failed')->and($error->attempts)->toBe(1)
                    ->and($error->getPrevious()->reason)->toBe('constructor_value_mismatch');
            }
        }
    } else {
        $request = $entry === 'pagination' ? new ItemsRequest() : new CompositeRequest();
        $handle = $client->send($request->setClient($client));
        if ($valid) {
            $data = $handle->dataOrFail();
            expect(($entry === 'pagination' ? $data[0] : $data)->value)->toBe('known');
        } else {
            expect($handle->raw()->exception->reason)->toBe('constructor_value_mismatch');
        }
    }
    expect($transport->getRecorded())->toHaveCount(1);
})->with(['pagination', 'composite', 'await'])->with([true, false]);

it('сравнивает словарь через HTTP и cached awaitAs с тем же гидратором', function (bool $valid): void {
    State::$value = ['a' => 1, 'b' => null];
    $input = $valid ? ['b' => null, 'a' => 1] : ['b' => 1, 'a' => 1];
    [$client, $transport] = ownedClient(['data' => ['value' => $input]], ConstructorOwnedFixture::rules());
    $handle = $client->send(new ArrayRequest());
    if ($valid) {
        expect($handle->dataOrFail()->value)->toBe(State::$value);
        expect($client->send(new ArrayRequest())->dataOrFail()->value)->toBe(State::$value);
    } else {
        expect($handle->raw()->exception->reason)->toBe('constructor_value_mismatch');
    }
    [$client, $transport] = ownedClient(
        ['data' => ['id' => 7, 'kind' => 'known', 'value' => $input]],
        ownedNodeRules()->withDto(ArrayDto::class, DtoRules::create()->field('value', FieldRule::create()->constructorValue()))
    );
    $handle = $client->send(new AwaitRequest());
    expect($handle->await()->value)->toBe('known');
    if ($valid) {
        expect($handle->awaitAs(ArrayDto::class)->value)->toBe(State::$value);
    } else {
        try {
            $handle->awaitAs(ArrayDto::class);
            throw new LogicException('Ожидалась ошибка повторного awaitAs');
        } catch (ContinuationAwaitException $error) {
            expect($error->reason)->toBe('final_hydration_failed')->and($error->getPrevious()->reason)->toBe('constructor_value_mismatch');
        }
    }
    expect($transport->getRecorded())->toHaveCount(1);
})->with([true, false]);

it('сохраняет проверенные источники и пути во вложенных списках и variants', function (string $shapeKind): void {
    $shape = match ($shapeKind) {
        'dto' => ValueShape::dto(NodeDto::class),
        'variants' => ValueShape::variants('type', ['node' => NodeDto::class]),
        default => ValueShape::list(ValueShape::dto(NodeDto::class)),
    };
    $nested = ['id' => 7, 'kind' => 'known', 'legacy' => 'unused', 'type' => 'node'];
    $rows = [['body' => $shapeKind === 'list' ? [$nested] : $nested, 'meta' => 'keep']];
    $rules = ownedNodeRules()->withDto(GraphDto::class, DtoRules::create()
        ->field('items', FieldRule::create()->from('rows')->shape(ValueShape::list($shape, each: 'body'))));
    $hydrator = Hydrator::forRules($rules);
    $dto = $hydrator->hydrate(['rows' => $rows], GraphDto::class);
    $node = $shapeKind === 'list' ? $dto->items[0][0] : $dto->items[0];
    expect($node->value)->toBe('known')->and($node->_extra['legacy'])->toBe('unused')->and(State::$calls)->toBe(1);
    $nested['kind'] = 'secret-value';
    $rows[0]['body'] = $shapeKind === 'list' ? [$nested] : $nested;
    $error = HydrationRulesFixture::error(fn () => $hydrator->hydrate(['rows' => $rows], GraphDto::class));
    expect($error->path)->toBe($shapeKind === 'list' ? 'items[0][0].value' : 'items[0].value')
        ->and($error->sourcePath)->toBe($shapeKind === 'list' ? '/rows/0/body/0/kind' : '/rows/0/body/kind')
        ->and($error->sourcePathKind)->toBe(SourcePathKind::Resolved);
})->with(['dto', 'variants', 'list']);

it('повторно сравнивает кешированный ответ по набору копии клиента', function (): void {
    [$client, $transport] = ownedClient(['data' => ['id' => 7, 'kind' => 'known', 'legacy' => 'other']]);
    expect($client->send(new NodeRequest())->dataOrFail()->value)->toBe('known');
    $rules = HydrationRules::create()->withDto(NodeDto::class, DtoRules::create()->extras('_extra')
        ->field('value', FieldRule::create()->from('legacy')->constructorValue()));
    $copy = new TestClient($client->getConfig()->with(hydration: new HydrationConfig(rules: $rules)), $transport);
    $result = $copy->send(new NodeRequest())->raw();
    expect($result->exception->reason)->toBe('constructor_value_mismatch')
        ->and($result->exception->sourcePath)->toBe('/data/legacy')->and($transport->getRecorded())->toHaveCount(1);
});

it('сохраняет исходящий To, ручной DTO и наследуемый конструктор без дополнительных проверок', function (): void {
    $class = InheritedDto::class;
    $rules = HydrationRules::create()->withDto($class, DtoRules::create()->extras('_extra')
        ->field('value', FieldRule::create()->constructorValue()));
    $dto = Hydrator::forRules($rules)->hydrate(['id' => 7, 'value' => 'known', 'future' => true], $class);
    expect($dto->toArray())->toBe(['kind' => 'known', 'id' => 7, '_extra' => ['future' => true]]);
    $manual = new $class(8, ['future' => true]);
    [$client, $transport] = ownedClient([], $rules);
    $client->send(new BodyRootRequest($manual))->dataOrFail();
    expect(json_decode($transport->getRecorded()[0]->body, true))->toBe(['kind' => 'known', 'id' => 8])
        ->and(State::$calls)->toBe(2);
});

it('доставляет mismatch через throwOnErrors и оставляет безопасную диагностику', function (): void {
    [$client, $transport] = ownedClient(['data' => ['id' => 7, 'kind' => 'synthetic-secret']]);
    $throwing = new TestClient($client->getConfig()->with(throwOnErrors: true), $transport);
    $error = HydrationRulesFixture::error(fn () => $throwing->send(new NodeRequest())->raw());
    expect($error->reason)->toBe('constructor_value_mismatch')
        ->and(json_encode($error->logContext(), JSON_THROW_ON_ERROR))->not->toContain('synthetic-secret');
});
