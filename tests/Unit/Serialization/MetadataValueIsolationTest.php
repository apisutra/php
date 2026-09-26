<?php

declare(strict_types=1);

use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Resolver\ClientRegistry;
use ApiSutra\Serialization\DtoSerializer;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Serializer;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\MetadataIsolation\AttributeDefaultDto;
use ApiSutra\Tests\Stubs\MetadataIsolation\BodyCastRequest;
use ApiSutra\Tests\Stubs\MetadataIsolation\BodyDtoRequest;
use ApiSutra\Tests\Stubs\MetadataIsolation\CastDto;
use ApiSutra\Tests\Stubs\MetadataIsolation\CastRequest;
use ApiSutra\Tests\Stubs\MetadataIsolation\CompositeDefaultsRequest;
use ApiSutra\Tests\Stubs\MetadataIsolation\CountingCast;
use ApiSutra\Tests\Stubs\MetadataIsolation\CreatedCastDto;
use ApiSutra\Tests\Stubs\MetadataIsolation\CreatedDefaultDto;
use ApiSutra\Tests\Stubs\MetadataIsolation\CreatedValue;
use ApiSutra\Tests\Stubs\MetadataIsolation\DefaultsDto;
use ApiSutra\Tests\Stubs\MetadataIsolation\DefaultsRequest;
use ApiSutra\Tests\Stubs\MetadataIsolation\FilledDefaultDto;
use ApiSutra\Tests\Stubs\MetadataIsolation\HeaderPathRequest;
use ApiSutra\Tests\Stubs\MetadataIsolation\MutableCounter;
use ApiSutra\Tests\Stubs\MetadataIsolation\PaginatedDefaultsRequest;
use ApiSutra\Tests\Stubs\MetadataIsolation\RecursiveDto;
use ApiSutra\Tests\Stubs\MetadataIsolation\ValuesDto;
use ApiSutra\Tests\Stubs\MetadataIsolation\ValuesRequest;
use ApiSutra\Tests\Stubs\MetadataIsolation\VariadicDto;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Pipeline\PipelineContext;

beforeEach(function (): void {
    CreatedValue::$created = 0;
    CreatedValue::$fail = false;
});

it('изолирует defaults конструктора, вложенных массивов и атрибутов на каждый DTO', function (bool $enabled): void {
    $hydrator = new Hydrator(new CastRegistry(), new AttributeMetadataCache($enabled));
    $items = $hydrator->hydrateCollection([[], [], []], DefaultsDto::class);
    $items[0]->state->value = 41;
    $nested = $items[0]->nested[0];
    $nested->value = 42;
    expect($items[1]->state)->not->toBe($items[0]->state)
        ->and($items[1]->state->value)->toBe(0)
        ->and($items[1]->nested[0]->value)->toBe(0)
        ->and($items[2]->state)->not->toBe($items[1]->state);
    $first = $hydrator->hydrate([], AttributeDefaultDto::class);
    $second = $hydrator->hydrate([], AttributeDefaultDto::class);
    $state = $first->states[0];
    $state->value = 99;
    expect($second->states[0]->value)->toBe(0);

    $from = DefaultsDto::from([]);
    $other = DefaultsDto::from([]);
    expect($from->state)->not->toBe($other->state)->and($from->nested[0])->not->toBe($other->nested[0]);
})->with([false, true]);

it('вычисляет default только для отсутствующего аргумента на обоих путях создания', function (bool $enabled, string $class): void {
    $hydrator = new Hydrator(new CastRegistry(), new AttributeMetadataCache($enabled));
    $supplied = new CreatedValue(42);
    CreatedValue::$created = 0;
    $first = $hydrator->hydrate(['label' => 'a', 'state' => $supplied], $class);
    $second = $hydrator->hydrate(['label' => 'b', 'state' => $supplied], $class);
    expect($first->state)->toBe($supplied)->and($second->state)->toBe($supplied)
        ->and(CreatedValue::$created)->toBe(0);
    $null = $hydrator->hydrate(['label' => 'null', 'state' => null], $class);
    expect($null->state)->toBeNull()->and(CreatedValue::$created)->toBe(0);
    $missing = $hydrator->hydrate(['label' => 'missing'], $class);
    $next = $hydrator->hydrate(['label' => 'next'], $class);
    expect(CreatedValue::$created)->toBe(2)->and($missing->state)->not->toBe($next->state);
})->with([false, true])->with([CreatedDefaultDto::class, FilledDefaultDto::class]);

it('доставляет ошибку выражения new из вызова конструктора и не вычисляет ненужный default', function (bool $enabled, string $class): void {
    $hydrator = new Hydrator(new CastRegistry(), new AttributeMetadataCache($enabled));
    CreatedValue::$fail = true;
    expect($hydrator->hydrate(['label' => 'ok', 'state' => null], $class)->state)->toBeNull();
    expect(fn () => $hydrator->hydrate(['label' => 'fail'], $class))->toThrow(RuntimeException::class, 'synthetic default failure');
    expect(CreatedValue::$created)->toBe(1);
    CreatedValue::$fail = false;
    expect($hydrator->hydrate(['label' => 'retry'], $class)->state)->toBeInstanceOf(CreatedValue::class)
        ->and(CreatedValue::$created)->toBe(2);
})->with([false, true])->with([CreatedDefaultDto::class, FilledDefaultDto::class]);

it('создаёт аргумент атрибута один раз на применение, включая missing и null', function (bool $enabled): void {
    $hydrator = new Hydrator(new CastRegistry(), new AttributeMetadataCache($enabled));
    foreach ([[], ['number' => null], ['number' => 0], ['number' => 0]] as $index => $payload) {
        $dto = $hydrator->hydrate($payload, CreatedCastDto::class);
        expect(CreatedValue::$created)->toBe($index + 1);
        expect($dto->number)->toBe($index < 2 ? null : 1);
    }
    $serializer = new DtoSerializer(new CastRegistry(), new AttributeMetadataCache($enabled));
    CreatedValue::$created = 0;
    foreach ([new CreatedCastDto(), new CreatedCastDto(0), new CreatedCastDto(0)] as $index => $dto) {
        $output = $serializer->serialize($dto);
        expect(CreatedValue::$created)->toBe($index + 1);
        if ($index > 0) {
            expect($output['number'])->toBe(1);
        }
    }
})->with([false, true]);

it('не удерживает и не повторно использует аргументы cast в гидраторе и DTO serializer', function (bool $enabled): void {
    $cache = new AttributeMetadataCache($enabled);
    $hydrator = new Hydrator(new CastRegistry(), $cache);
    $serializer = new DtoSerializer(new CastRegistry(), $cache);
    foreach ([CastDto::class, CreatedCastDto::class, CastDto::class] as $class) {
        for ($i = 0; $i < 2; $i++) {
            expect($hydrator->hydrate(['number' => 0], $class)->number)->toBe(1)
                ->and($serializer->serialize(new $class(0))['number'])->toBe(1);
        }
    }
})->with([false, true]);

it('доставляет ошибку нового аргумента атрибута из прогретого кеша и допускает следующий вызов', function (bool $enabled): void {
    $cache = new AttributeMetadataCache($enabled);
    $hydrator = new Hydrator(new CastRegistry(), $cache);
    $serializer = new DtoSerializer(new CastRegistry(), $cache);
    $hydrator->hydrate(['number' => 0], CreatedCastDto::class);
    $serializer->serialize(new CreatedCastDto(0));
    CreatedValue::$fail = true;
    expect(fn () => $hydrator->hydrate(['number' => 0], CreatedCastDto::class))->toThrow(RuntimeException::class)
        ->and(fn () => $serializer->serialize(new CreatedCastDto(0)))->toThrow(RuntimeException::class);
    CreatedValue::$fail = false;
    expect($hydrator->hydrate(['number' => 0], CreatedCastDto::class)->number)->toBe(1)
        ->and($serializer->serialize(new CreatedCastDto(0))['number'])->toBe(1)
        ->and(CreatedValue::$created)->toBe(6);
})->with([false, true]);

it('изолирует cast query, body и вложенного DTO, сохраняя header/path без cast', function (bool $enabled): void {
    $serializer = new Serializer(new CastRegistry(), new AttributeMetadataCache($enabled));
    $config = new ClientConfig(baseUrl: 'https://isolation.test');
    for ($i = 0; $i < 2; $i++) {
        foreach ([CastRequest::class, BodyCastRequest::class, BodyDtoRequest::class, HeaderPathRequest::class] as $class) {
            $request = new $class();
            $prepared = $serializer->serialize($request, new PipelineContext($request, $config, 'isolation'));
            if ($request instanceof CastRequest) {
                parse_str((string) parse_url($prepared->url, PHP_URL_QUERY), $query);
                expect($query['number'])->toBe('1');
            } elseif ($request instanceof HeaderPathRequest) {
                expect($prepared->headers['X-Number'])->toBe('0')
                    ->and($prepared->url)->toBe('https://isolation.test/items/0');
            } else {
                expect(json_decode($prepared->body, true, flags: JSON_THROW_ON_ERROR)['number'])->toBe(1);
            }
        }
    }
})->with([false, true]);

it('сохраняет scalar и enum defaults при прогреве и повторно использует безопасные metadata', function (bool $enabled): void {
    $cache = new AttributeMetadataCache($enabled);
    $cache->warmup([ValuesDto::class]);
    $hydrator = new Hydrator(new CastRegistry(), $cache);
    $serializer = new DtoSerializer(new CastRegistry(), $cache);
    $first = $hydrator->hydrate([], ValuesDto::class);
    expect($hydrator->hydrate([], VariadicDto::class)->numbers)->toBe([]);
    expect($first->id)->toBe(7)->and($first->environment)->toBe(Environment::Production)
        ->and($first->values)->toBe(['env' => [Environment::Testing]]);
    $serializer->serialize($first);
    $beforeHydration = $cache->get(ValuesDto::class . ':hydration-plan');
    $beforeSerialization = $cache->get(ValuesDto::class . ':serialization-plan');
    for ($i = 0; $i < 3; $i++) {
        $dto = $hydrator->hydrate(['record_id' => '9'], ValuesDto::class);
        expect($dto->id)->toBe(9);
        $serializer->serialize($dto);
    }
    expect($cache->get(ValuesDto::class . ':hydration-plan'))->toBe($beforeHydration)
        ->and($cache->get(ValuesDto::class . ':serialization-plan'))->toBe($beforeSerialization);
    if ($enabled) {
        $plan = $beforeHydration['plans'][$hydrator->descriptions()->forClass(ValuesDto::class)];
        expect($plan->bind())->toBe($plan)
            ->and($beforeSerialization['plan']->bind())->toBe($beforeSerialization['plan']->bind());
    }
    $requests = new Serializer(new CastRegistry(), $cache);
    $request = new ValuesRequest();
    $context = new PipelineContext($request, new ClientConfig(baseUrl: 'https://isolation.test'), 'values');
    $requests->serialize($request, $context);
    $beforeRequest = $cache->get(ValuesRequest::class . ':request-parts-plan');
    for ($i = 0; $i < 3; $i++) {
        $requests->serialize($request, $context);
    }
    expect($cache->get(ValuesRequest::class . ':request-parts-plan'))->toBe($beforeRequest);
    if ($enabled) {
        expect($beforeRequest['plan']->bind())->toBe($beforeRequest['plan']);
    }
})->with([false, true]);

it('сохраняет намеренно общий cast клиента между запросами', function (): void {
    $counter = new MutableCounter();
    $cast = new CountingCast($counter);
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake([ValuesRequest::class => MockResponse::success([])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://isolation.test', casts: ['int' => $cast]), $transport);
    $client->send(new ValuesRequest())->dataOrFail();
    $client->send(new ValuesRequest())->dataOrFail();
    $queries = [];
    foreach ($transport->getRecorded() as $request) {
        parse_str((string) parse_url($request->url, PHP_URL_QUERY), $query);
        $queries[] = $query['number'];
    }
    expect($queries)->toBe(['1', '2'])->and($counter->value)->toBe(2);
});

it('разделяет значения рекурсивных узлов и не удерживает их в прогретом кеше', function (bool $enabled): void {
    $cache = new AttributeMetadataCache($enabled);
    $hydrator = new Hydrator(new CastRegistry(), $cache);
    $tree = $hydrator->hydrate(['child' => ['child' => []]], RecursiveDto::class);
    expect(CreatedValue::$created)->toBe(3)
        ->and($tree->state)->not->toBe($tree->child->state)
        ->and($tree->child->state)->not->toBe($tree->child->child->state);
    $reference = WeakReference::create($tree->state);
    unset($tree);
    gc_collect_cycles();
    expect($reference->get())->toBeNull();
    $next = $hydrator->hydrate([], RecursiveDto::class);
    expect($next->state->value)->toBe(0)->and(CreatedValue::$created)->toBe(4);
})->with([false, true]);

it('изолирует Returns, пагинацию и CompositeFlow одного клиента во всех окружениях', function (?Environment $environment): void {
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake([
        DefaultsRequest::class => MockResponse::success(['value' => 'wire']),
        PaginatedDefaultsRequest::class => MockResponse::make('{"data":[{},{}]}'),
    ]);
    $config = new ClientConfig(baseUrl: 'https://isolation.test');
    $client = new TestClient($environment === null ? $config : $config->with(environment: $environment), $transport);
    $first = $client->send(new DefaultsRequest())->dataOrFail();
    $first->state->value = 77;
    $second = $client->send(new DefaultsRequest())->dataOrFail();
    expect($second->state->value)->toBe(0);
    $page = $client->send(new PaginatedDefaultsRequest())->dataOrFail();
    $items = $page->items();
    expect($items[0]->state)->not->toBe($items[1]->state)->and($items[0]->state->value)->toBe(0);
    $page->state->value = 99;
    expect($page->withItems([])->state)->toBe($page->state);
    $nextPage = $client->send(new PaginatedDefaultsRequest())->dataOrFail();
    expect($nextPage->state->value)->toBe(0);
    $aggregate = $client->send((new CompositeDefaultsRequest())->setClient($client))->dataOrFail();
    expect($aggregate->value)->toBe('aggregate')->and($aggregate->state->value)->toBe(0);
    expect($transport->getRecorded())->toHaveCount(5);
})->with([null, Environment::Local, Environment::Testing]);

it('изолирует операции долгоживущего клиента из общего реестра', function (): void {
    $registry = new ClientRegistry();
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake([DefaultsRequest::class => MockResponse::make('{}')]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://isolation.test'), $transport);
    $registry->register($client, 'ApiSutra\\Tests\\Stubs\\MetadataIsolation');
    $firstClient = $registry->resolve(DefaultsRequest::class);
    $first = $firstClient->send(new DefaultsRequest())->dataOrFail();
    $first->state->value = 91;
    unset($first, $firstClient);
    gc_collect_cycles();
    $secondClient = $registry->resolve(DefaultsRequest::class);
    $second = $secondClient->send(new DefaultsRequest())->dataOrFail();
    expect($secondClient)->toBe($client)->and($second->state->value)->toBe(0);
    $other = new TestClient(new ClientConfig(baseUrl: 'https://isolation.test'), $transport);
    expect($other->send(new DefaultsRequest())->dataOrFail()->state)->not->toBe($second->state);
});
