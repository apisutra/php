<?php

declare(strict_types=1);

use ApiSutra\Config\HydrationConfig;
use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Tests\Stubs\HydrationRules\CountedRecordDto;
use ApiSutra\Tests\Stubs\HydrationRules\CountedRecordCast;
use ApiSutra\Tests\Stubs\Requests\HydrationProbeRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DefaultSpec;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HandlerSpec;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Serialization\Rules\SourcePathKind;
use ApiSutra\Serialization\Rules\ValueShape;
use ApiSutra\Tests\Stubs\HydrationRules\CountingCast;
use ApiSutra\Tests\Stubs\HydrationRules\FreshObjectProvider;
use ApiSutra\Tests\Stubs\HydrationRules\IncomingAttributesDto;
use ApiSutra\Tests\Stubs\HydrationRules\ProfiledDto;
use ApiSutra\Tests\Stubs\HydrationRules\RecordDto;
use ApiSutra\Tests\Stubs\HydrationRules\ReturnCast;
use ApiSutra\Tests\Stubs\HydrationRules\RowHydrationHelper;
use ApiSutra\Tests\Stubs\HydrationRules\ScalarDto;
use ApiSutra\Tests\Stubs\HydrationRules\ScopedAttributeDto;
use ApiSutra\Tests\Stubs\HydrationRules\ScopedChildCast;
use ApiSutra\Tests\Stubs\HydrationRules\ScopedChildProvider;
use ApiSutra\Tests\Stubs\HydrationRules\ScopedRowCast;
use ApiSutra\Tests\Stubs\HydrationRules\ValueDto;
use ApiSutra\Tests\Support\HydrationRulesFixture;

it('вызывает методы с направленным контекстом атрибутных cast, itemCast и provider с тем же набором', function (): void {
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
        ->withDto(RecordDto::class, DtoRules::create()->field('id', FieldRule::create()->from('record_id')));
    $payload = ['child' => ['record_id' => 1], 'rows' => [[['record_id' => 2]]], 'provided' => ['record_id' => 3]];
    $hydrator = Hydrator::forRules($rules);
    $dto = $hydrator->hydrate($payload, ScopedAttributeDto::class);
    expect($dto->child->id)->toBe(1)->and($dto->rows[0][0]->id)->toBe(2)->and($dto->provided->id)->toBe(3);
    foreach (['child', 'rows', 'provided'] as $field) {
        $invalid = $payload;
        if ($field === 'rows') {
            $invalid[$field][0][0]['record_id'] = 'bad';
        } else {
            $invalid[$field]['record_id'] = 'bad';
        }
        $error = HydrationRulesFixture::error(fn () => $hydrator->hydrate($invalid, ScopedAttributeDto::class));
        expect($error->reason)->toBe('invalid_field_type')->and($error->path)->toStartWith($field)
            ->and($error->sourcePathKind)->toBe(SourcePathKind::Boundary);
    }
});

it('передаёт scope в профиль и игнорирует defaults набора у профилированного родителя', function (): void {
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
        ->withDto(RecordDto::class, DtoRules::create()->field('id', FieldRule::create()->from('record_id')));
    $hydrator = Hydrator::forRules($rules);
    $dto = $hydrator->hydrate(['child' => ['record_id' => 1], 'count' => '2'], ProfiledDto::class);
    expect($dto->child->id)->toBe(1)->and($dto->count)->toBe(2);
    $error = HydrationRulesFixture::error(fn () => $hydrator->hydrate(['child' => ['record_id' => '1'], 'count' => '2'], ProfiledDto::class));
    expect($error->reason)->toBe('invalid_field_type')->and($error->path)->toBe('child.id');
    expect(fn () => Hydrator::forRules($rules->withDto(ProfiledDto::class, DtoRules::create())))
        ->toThrow(ConfigurationException::class);
});

it('передаёт scope из внешних descriptor и сохраняет явную границу default гидратора', function (string $registration): void {
    $field = match ($registration) {
        'cast' => FieldRule::create()->cast(new HandlerSpec(ScopedChildCast::class), ValueShape::dto(RecordDto::class)),
        'provider' => FieldRule::create()->default(DefaultSpec::provider(new HandlerSpec(ScopedChildProvider::class), ValueState::Present)),
        'itemCast' => FieldRule::create()->shape(ValueShape::list(
            ValueShape::list(ValueShape::dto(RecordDto::class)),
            itemCast: new HandlerSpec(ScopedRowCast::class),
        )),
    };
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
        ->withDto(ValueDto::class, DtoRules::create()->field('value', $field));
    $payload = $registration === 'itemCast' ? [[['id' => 1]]] : ['id' => 1];
    $result = Hydrator::forRules($rules)->hydrate(['value' => $payload], ValueDto::class)->value;
    expect($registration === 'itemCast' ? $result[0][0]->id : $result->id)->toBe(1);
    $invalid = $registration === 'itemCast' ? [[['id' => '1']]] : ['id' => '1'];
    expect(HydrationRulesFixture::error(fn () => Hydrator::forRules($rules)->hydrate(['value' => $invalid], ValueDto::class))->reason)
        ->toBe('invalid_field_type');
    expect(RowHydrationHelper::hydrate([['id' => '1']], null)[0]->id)->toBe(1);
})->with(['cast', 'provider', 'itemCast']);

it('создаёт HandlerSpec отдельно для элементов и вызовов и проверяет результат cast', function (): void {
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
        ->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()->shape(ValueShape::list(
            ValueShape::int(),
            itemCast: new HandlerSpec(CountingCast::class),
        ))));
    foreach ([Hydrator::forRules($rules), Hydrator::forRules($rules)] as $hydrator) {
        foreach ([0, 1] as $call) {
            expect($hydrator->hydrate(['value' => [0, 0]], ValueDto::class)->value)->toBe([1, 1]);
        }
    }
    foreach ([['int', '7', false], ['int', 7, true], ['float', 7, true]] as [$field, $return, $valid]) {
        $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
            ->withDto(ScalarDto::class, DtoRules::create()->field($field, FieldRule::create()->cast(new HandlerSpec(ReturnCast::class, [$return]))));
        $hydrate = fn (): object => Hydrator::forRules($rules)->hydrate([$field => 'input'], ScalarDto::class);
        if ($valid) {
            expect($hydrate()->{$field})->toBe($field === 'float' ? 7.0 : 7);
        } else {
            $error = HydrationRulesFixture::error($hydrate);
            expect($error->reason)->toBe('invalid_field_type')->and($error->sourcePathKind)->toBe(SourcePathKind::Boundary);
        }
    }
});

it('не разделяет объектные defaults провайдера и не принимает объекты в literal декларации', function (): void {
    $rules = HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()->default(DefaultSpec::provider(new HandlerSpec(FreshObjectProvider::class)))));
    $hydrator = Hydrator::forRules($rules);
    $first = $hydrator->hydrate([], ValueDto::class)->value;
    $second = $hydrator->hydrate([], ValueDto::class)->value;
    expect($first)->toBeInstanceOf(stdClass::class)->and($second)->not->toBe($first);
    expect(fn () => DefaultSpec::value(['nested' => [new stdClass()]]))->toThrow(ConfigurationException::class)
        ->and(fn () => new HandlerSpec(ReturnCast::class, [['nested' => new stdClass()]]))->toThrow(ConfigurationException::class);
});

it('отклоняет пересечение внешнего правила с каждым входным атрибутом', function (string $field): void {
    $rules = HydrationRules::create()->withDto(IncomingAttributesDto::class, DtoRules::create()->field($field, FieldRule::create()));
    expect(fn () => Hydrator::forRules($rules))->toThrow(ConfigurationException::class);
})->with(['from', 'map', 'nested', 'cast', 'date', 'empty', 'default']);

it('изолирует два набора на общем reflection cache и ClientConfig копиях', function (): void {
    $strict = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict));
    $legacy = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Legacy));
    $cache = new AttributeMetadataCache();
    $a = new Hydrator(new CastRegistry(), $cache, config: new HydrationConfig(rules: $strict));
    $b = new Hydrator(new CastRegistry(), $cache, config: new HydrationConfig(rules: $legacy));
    foreach ([$a, $b, $b, $a] as $hydrator) {
        if ($hydrator === $a) {
            expect(HydrationRulesFixture::error(fn () => $hydrator->hydrate(['int' => '7'], ScalarDto::class))->reason)->toBe('invalid_field_type');
        } else {
            expect($hydrator->hydrate(['int' => '7'], ScalarDto::class)->int)->toBe(7);
        }
    }
    $config = new ClientConfig(baseUrl: 'https://rules.test', hydration: new HydrationConfig(rules: $strict));
    expect($config->with()->hydration->rules)->toBe($strict)
        ->and($config->with(hydration: new HydrationConfig(rules: $legacy))->hydration->rules)->toBe($legacy)
        ->and($config->with(hydration: null)->hydration)->toBeNull()
        ->and(Hydrator::default()->hydrate(['int' => '7'], ScalarDto::class)->int)->toBe(7);
});

it('выполняет each, itemCast с контекстом и приём готового DTO ровно один раз в standalone и Returns', function (bool $http): void {
    CountedRecordDto::$constructed = 0;
    CountedRecordCast::$contexts = [];
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
        ->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()->shape(ValueShape::list(
            ValueShape::dto(CountedRecordDto::class),
            each: 'row',
            itemCast: new HandlerSpec(CountedRecordCast::class),
        ))))
        ->withDto(CountedRecordDto::class, DtoRules::create()->field('id', FieldRule::create()->from('record_id')));
    $payload = ['value' => [['row' => ['record_id' => 7]]]];
    if ($http) {
        $transport = new MockTransport();
        $transport->fake(['*' => MockResponse::success($payload)]);
        $client = new TestClient(new ClientConfig(baseUrl: 'https://scope.test', hydration: new HydrationConfig(rules: $rules)), $transport);
        $dto = $client->send(new HydrationProbeRequest(ValueDto::class))->dataOrFail();
        expect(CountedRecordCast::$contexts[0]->request)->toBeInstanceOf(HydrationProbeRequest::class)
            ->and(CountedRecordCast::$contexts[0]->response)->not->toBeNull();
    } else {
        $dto = Hydrator::forRules($rules)->hydrate($payload, ValueDto::class);
        expect(CountedRecordCast::$contexts)->toBe([null]);
    }
    expect($dto->value[0]->id)->toBe(7)->and(CountedRecordDto::$constructed)->toBe(1);
})->with([false, true]);
