<?php

declare(strict_types=1);

use ApiSutra\Config\CacheConfig;
use ApiSutra\Continuation\ContinuationState;
use ApiSutra\Enums\Serialization\EnumOutput;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Request\PaginationOptions;
use ApiSutra\Tests\Stubs\Continuation\WithoutCriterionRequest;
use ApiSutra\Tests\Stubs\Continuation\RecordingStateResolver;
use ApiSutra\Tests\Stubs\Dto\ProfileDrivenOverrideDto;
use ApiSutra\Tests\Stubs\Enums\TitleStatus;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\ArrayCache;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Testing\MockResponse;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Tests\Stubs\Hydration\ContractRequest;
use ApiSutra\Tests\Stubs\Hydration\PlainValueDto;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Tests\Stubs\MappingExecution\Provider;
use ApiSutra\Tests\Stubs\MappingExecution\ProfileDto;
use ApiSutra\Serialization\Rules\DefaultSpec;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\SerializationException;
use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;
use ApiSutra\Serialization\DtoSerializer;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Integration\HttpMappingContext;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HandlerSpec;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Serialization\Rules\SourcePathKind;
use ApiSutra\Tests\Stubs\HydrationRules\RecordDto;
use ApiSutra\Tests\Stubs\HydrationRules\ValueDto;
use ApiSutra\Tests\Stubs\MappingExecution\InputDto;
use ApiSutra\Tests\Stubs\MappingExecution\InputHandler;
use ApiSutra\Tests\Stubs\MappingExecution\OutputDto;
use ApiSutra\Tests\Stubs\MappingExecution\OutputHandler;
use ApiSutra\Tests\Stubs\Requests\HydrationProbeRequest;
use ApiSutra\Tests\Support\HydrationRulesFixture;
use ApiSutra\VO\Pipeline\PipelineContext;

beforeEach(function (): void {
    InputHandler::$action = OutputHandler::$action = null;
    InputHandler::$constructed = OutputHandler::$constructed = 0;
});
afterEach(function (): void {
    InputHandler::$action = OutputHandler::$action = null;
});

it('принимает одно направление и проверяет противоположное только при выборе cast', function (): void {
    $hydrator = new Hydrator(new CastRegistry(), config: new HydrationConfig());
    $serializer = new DtoSerializer(new CastRegistry(), config: new HydrationConfig());
    expect($hydrator->hydrate(['value' => 7], InputDto::class)->value)->toBe(7)
        ->and($serializer->serialize(new OutputDto(7, ['secret' => 'value'])))->toBe(['value' => 7]);
    expect(fn () => $hydrator->hydrate(['value' => 7], OutputDto::class))
        ->toThrow(ConfigurationException::class, 'Hydration cast');
    expect(fn () => $serializer->serialize(new InputDto(7)))
        ->toThrow(ConfigurationException::class, 'Serialization cast');
    expect(InputHandler::$constructed)->toBe(1)->and(OutputHandler::$constructed)->toBe(1);
    expect($hydrator->hydrate(['value' => null], OutputDto::class)->value)->toBeNull()
        ->and($hydrator->hydrate([], OutputDto::class)->value)->toBeNull()
        ->and($serializer->serialize(new InputDto()))->toBe([]);
});

it('сохраняет правила и Boundary при вложенной гидратации из input-only cast', function (): void {
    InputHandler::$action = fn ($value, HydrationContext $context) => $context->hydrateCollection(items: $value, class: RecordDto::class);
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
        ->withDto(RecordDto::class, DtoRules::create()->field('id', FieldRule::create()->from('record_id')));
    $hydrator = Hydrator::forRules($rules);
    expect($hydrator->hydrate(['value' => [['record_id' => 7]]], InputDto::class)->value[0]->id)->toBe(7);
    $error = HydrationRulesFixture::error(fn () => $hydrator->hydrate(['value' => [['record_id' => '7']]], InputDto::class));
    expect($error->path)->toBe('value[0].id')->and($error->sourcePathKind)->toBe(SourcePathKind::Boundary);
    expect($hydrator->hydrate(['value' => [['record_id' => 8]]], InputDto::class)->value[0]->id)->toBe(8);
});

it('закрывает каждый контекст input и output при успехе и исключении', function (bool $throws): void {
    $contexts = [];
    $failure = new LogicException('handler failure');
    InputHandler::$action = function ($value, HydrationContext $context) use (&$contexts, $throws, $failure) {
        $contexts[] = $context;
        expect($context->extension(HttpMappingContext::class))->toBeNull();
        if ($throws) {
            throw $failure;
        }
        return $value;
    };
    OutputHandler::$action = function ($value, SerializationContext $context) use (&$contexts, $throws, $failure) {
        $contexts[] = $context;
        if ($throws) {
            throw $failure;
        }
        return $value;
    };
    $hydrator = Hydrator::default();
    $serializer = new DtoSerializer(new CastRegistry());
    foreach ([0, 1] as $call) {
        foreach ([fn () => $hydrator->hydrate(['value' => 1], InputDto::class), fn () => $serializer->serialize(new OutputDto(1))] as $operation) {
            if ($throws) {
                expect($operation)->toThrow($failure);
            } else {
                $operation();
            }
        }
    }
    expect(array_unique(array_map(spl_object_id(...), $contexts)))->toHaveCount(4);
    foreach ($contexts as $context) {
        expect(fn () => $context->extension(HttpMappingContext::class))->toThrow(ConfigurationException::class);
        if ($context instanceof HydrationContext) {
            expect(fn () => $context->hydrate([], InputDto::class))->toThrow(ConfigurationException::class)
                ->and(fn () => $context->hydrateCollection([], InputDto::class))->toThrow(ConfigurationException::class);
        } else {
            expect(fn () => $context->serialize(new InputDto()))->toThrow(ConfigurationException::class);
        }
    }
})->with([false, true]);

it('не удерживает pipeline и payload сохранённым закрытым контекстом', function (): void {
    $saved = [];
    InputHandler::$action = OutputHandler::$action = function ($value, $context) use (&$saved) {
        $saved[] = $context;
        return $value;
    };
    $request = new HydrationProbeRequest(InputDto::class);
    $pipeline = new PipelineContext($request, new ClientConfig(baseUrl: 'https://context.test'), 'trace');
    $payload = new stdClass();
    $weakPipeline = WeakReference::create($pipeline);
    $weakPayload = WeakReference::create($payload);
    Hydrator::default()->hydrate(['value' => $payload], InputDto::class, $pipeline);
    (new DtoSerializer(new CastRegistry()))->serialize(new OutputDto($payload), $pipeline);
    unset($pipeline, $payload, $request);
    gc_collect_cycles();
    expect($saved)->toHaveCount(2)->and($weakPipeline->get())->toBeNull()->and($weakPayload->get())->toBeNull();
});

it('даёт снимок текущих HTTP-ссылок без доступа к pipeline и config', function (): void {
    $request = new HydrationProbeRequest(InputDto::class);
    $pipeline = new PipelineContext($request, new ClientConfig(baseUrl: 'https://context.test'), 'trace', options: RequestOptions::empty(), paginationOptions: PaginationOptions::empty());
    $response = new ProviderResponse(200, [], '{}', new PreparedRequest(HttpMethod::GET, 'https://context.test'), 0);
    $pipeline->response = $response;
    $pipeline->lastResponse = $response;
    $snapshots = [];
    InputHandler::$action = function ($value, HydrationContext $context) use ($pipeline, &$snapshots) {
        $snapshot = $context->extension(HttpMappingContext::class);
        expect($context->extension(PipelineContext::class))->toBeNull()->and($context->extension(ClientConfig::class))->toBeNull();
        expect($snapshot->request)->toBe($pipeline->request)->and($snapshot->response)->toBe($pipeline->response)
            ->and($snapshot->traceId)->toBe('trace')->and($snapshot->role)->toBe($pipeline->role)
            ->and($snapshot->options)->toBe($pipeline->options)->and($snapshot->paginationOptions)->toBe($pipeline->paginationOptions);
        $snapshots[] = $snapshot;
        $pipeline->response = null;
        return $value;
    };
    Hydrator::default()->hydrate(['value' => 1], InputDto::class, $pipeline);
    Hydrator::default()->hydrate(['value' => 2], InputDto::class, $pipeline);
    expect($snapshots[0])->not->toBe($snapshots[1])
        ->and($snapshots[0]->response)->toBe($response)->and($snapshots[1]->response)->toBeNull();
});

it('продолжает выходную ветку через context, обнаруживает цикл и восстанавливается', function (): void {
    $serializer = new DtoSerializer(new CastRegistry());
    OutputHandler::$action = fn ($value, SerializationContext $context) => $context->serialize($value);
    expect($serializer->serialize(new OutputDto(new RecordDto(7))))->toBe(['value' => ['id' => 7], '_extra' => []]);
    $root = new OutputDto('start');
    OutputHandler::$action = fn ($value, SerializationContext $context) => $context->serialize($root);
    expect(fn () => $serializer->serialize($root))->toThrow(SerializationException::class, 'Circular reference');
    OutputHandler::$action = fn ($value, SerializationContext $context) => $context->serialize(new RecordDto(8));
    expect($serializer->serialize($root)['value'])->toBe(['id' => 8]);
});

it('проверяет направление registry и внешних правил без fallback', function (): void {
    $registry = new CastRegistry();
    $registry->register('int', new InputHandler());
    $serializer = new DtoSerializer($registry);
    expect(fn () => $serializer->serializeWithPolicy(new RecordDto(7), new DtoSerializationPolicy(), casts: $registry))
        ->toThrow(ConfigurationException::class, 'Serialization cast');
    $registry->register('int', OutputHandler::class);
    expect($serializer->serializeWithPolicy(new RecordDto(7), new DtoSerializationPolicy(), casts: $registry))->toBe(['id' => 7]);
    foreach ([FieldRule::create()->cast(new HandlerSpec(OutputHandler::class))] as $field) {
        expect(fn () => Hydrator::forRules(HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()->field('value', $field))))
            ->toThrow(ConfigurationException::class);
    }
    expect(fn () => (new InputHandler())->hydrate(1))->toThrow(ArgumentCountError::class);
});

it('разрешает профиль дочернего DTO в DX и наследует explicit policy через context', function (): void {
    OutputHandler::$action = fn ($value, SerializationContext $context) => $context->serialize(new ProfileDrivenOverrideDto(TitleStatus::Active));
    $serializer = new DtoSerializer(new CastRegistry());
    expect($serializer->serialize(new OutputDto('create'))['value'])->toBe(['status' => ['value' => 'active', 'title' => 'Active']]);
    expect($serializer->serializeWithPolicy(new OutputDto('create'), new DtoSerializationPolicy(enumOutput: EnumOutput::Name, serializeNulls: false))['value'])
        ->toBe(['status' => 'Active']);
});

it('сохраняет receiver preflight до конструктора cast и позволяет созданный внутри DTO', function (): void {
    $serializer = new DtoSerializer(new CastRegistry(), config: new HydrationConfig());
    expect(fn () => $serializer->serialize(new OutputDto(new OutputDto(7, ['secret' => 1]))))
        ->toThrow(SerializationException::class, 'receiver');
    expect(OutputHandler::$constructed)->toBe(0);
    OutputHandler::$action = fn ($value, SerializationContext $context) => $value === 'create'
        ? $context->serialize(new OutputDto(7, ['secret' => 1])) : $value;
    expect($serializer->serialize(new OutputDto('create')))->toBe(['value' => ['value' => 7]]);
});

it('оставляет внешний контекст активным пока вложенный вызов закрывает свой', function (): void {
    $inner = null;
    InputHandler::$action = function ($value, HydrationContext $context) use (&$inner) {
        if ($value === 1) {
            $inner = $context;
            return 1;
        }
        $dto = $context->hydrate(['value' => 1], InputDto::class);
        expect($context->extension(HttpMappingContext::class))->toBeNull();
        expect(fn () => $inner->extension(HttpMappingContext::class))->toThrow(ConfigurationException::class);
        return $dto->value;
    };
    expect(Hydrator::default()->hydrate(['value' => 2], InputDto::class)->value)->toBe(1);
});

it('сохраняет текущий ответ при HTTP cache hit и не создаёт HTTP extension для await', function (): void {
    $snapshots = [];
    InputHandler::$action = function ($value, HydrationContext $context) use (&$snapshots) {
        $snapshots[] = $context->extension(HttpMappingContext::class);
        return $value;
    };
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['value' => 7])]);
    $rules = HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()->cast(new HandlerSpec(InputHandler::class))));
    $resolver = new RecordingStateResolver();
    $resolver->state = ContinuationState::ready(['value' => 8]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://context.test',
        cacheConfig: new CacheConfig(store: new ArrayCache()),
        hydration: new HydrationConfig(rules: $rules),
        continuationStateResolver: $resolver,
    ), $transport);
    foreach ([0, 1] as $call) {
        expect($client->send(new HydrationProbeRequest(InputDto::class))->dataOrFail()->value)->toBe(7);
    }
    expect($transport->getRecorded())->toHaveCount(1);
    expect($snapshots[0]->response)->not->toBeNull()->and($snapshots[1]->response)->not->toBeNull();
    $handle = $client->send(new WithoutCriterionRequest());
    expect($handle->awaitAs(InputDto::class)->value)->toBe(8)
        ->and($handle->awaitAs(ValueDto::class)->value)->toBe(8);
    expect($snapshots)->toHaveCount(4)->and($snapshots[2])->toBeNull()->and($snapshots[3])->toBeNull();
});

it('даёт provider тот же контекст для всех состояний и закрывает его после ошибки', function (): void {
    $states = [];
    $contexts = [];
    Provider::$action = function ($value, ValueState $state, array $source, HydrationContext $context) use (&$states, &$contexts) {
        $states[] = $state;
        $contexts[] = $context;
        return $context->hydrate(['id' => 9], RecordDto::class);
    };
    try {
        $rule = FieldRule::create()->default(DefaultSpec::provider(new HandlerSpec(Provider::class), ...ValueState::cases()));
        $hydrator = Hydrator::forRules(HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()->field('value', $rule)));
        foreach ([[], ['value' => null], ['value' => ''], ['value' => 'present']] as $data) {
            expect($hydrator->hydrate($data, ValueDto::class)->value->id)->toBe(9);
        }
        expect($states)->toBe([ValueState::Missing, ValueState::Null, ValueState::Present, ValueState::Present]);
        Provider::$action = function ($value, ValueState $state, array $source, HydrationContext $context) use (&$contexts) {
            $contexts[] = $context;
            throw new LogicException('provider failed');
        };
        expect(fn () => $hydrator->hydrate([], ValueDto::class))->toThrow(LogicException::class, 'provider failed');
        foreach ($contexts as $context) {
            expect(fn () => $context->extension(HttpMappingContext::class))->toThrow(ConfigurationException::class);
        }
    } finally {
        Provider::$action = null;
    }
});

it('разделяет выходной профиль и входные атрибуты при общем receiver discovery', function (): void {
    OutputHandler::$action = fn ($value, SerializationContext $context) => $value + 10;
    $hydrator = new Hydrator(new CastRegistry(), config: new HydrationConfig());
    $dto = $hydrator->hydrate(['id' => 7, 'future' => true], ProfileDto::class);
    expect($dto->id)->toBe(7)->and($dto->_extra)->toBe(['future' => true]);
    $serializer = new DtoSerializer(new CastRegistry(), config: new HydrationConfig());
    expect($serializer->serialize($dto))->toBe(['id' => 17]);
});

it('переиспользует registry handler между клиентами с новым закрываемым контекстом на каждый вызов', function (): void {
    $handler = new OutputHandler();
    $contexts = [];
    $traces = [];
    OutputHandler::$action = function ($value, SerializationContext $context) use (&$contexts, &$traces) {
        $contexts[] = $context;
        $http = $context->extension(HttpMappingContext::class);
        expect($http->request)->toBeInstanceOf(ContractRequest::class)->and($http->response)->toBeNull();
        $traces[] = $http->traceId;
        return strtoupper($value);
    };
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['value' => 'ok'])]);
    $config = new ClientConfig(baseUrl: 'https://context.test', casts: ['string' => $handler]);
    $a = new TestClient($config, $transport);
    $b = new TestClient($config->with(), $transport);
    foreach ([$a, $b, $a] as $client) {
        expect($client->send(new ContractRequest(PlainValueDto::class, value: 'input'))->dataOrFail()->value)->toBe('ok');
    }
    expect(OutputHandler::$constructed)->toBe(1)->and(array_unique($traces))->toHaveCount(3)
        ->and(array_unique(array_map(spl_object_id(...), $contexts)))->toHaveCount(3);
    foreach ($contexts as $context) {
        expect(fn () => $context->extension(HttpMappingContext::class))->toThrow(ConfigurationException::class);
    }
});
