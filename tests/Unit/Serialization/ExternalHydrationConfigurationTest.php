<?php

declare(strict_types=1);

use ApiSutra\Enums\DataTransfer\EmptyStringBehavior;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DefaultSpec;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HandlerSpec;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Serialization\Rules\ValueShape;
use ApiSutra\Tests\Stubs\HydrationRules\ArrayReceiverDto;
use ApiSutra\Tests\Stubs\HydrationRules\CollectionDto;
use ApiSutra\Tests\Stubs\HydrationRules\DateReceiverDto;
use ApiSutra\Tests\Stubs\HydrationRules\HydratePolicyDto;
use ApiSutra\Tests\Stubs\HydrationRules\InvalidReceiversDto;
use ApiSutra\Tests\Stubs\HydrationRules\JsonReceiverDto;
use ApiSutra\Tests\Stubs\HydrationRules\NormalizerDto;
use ApiSutra\Tests\Stubs\HydrationRules\OutputAttributesDto;
use ApiSutra\Tests\Stubs\HydrationRules\PlainChildDto;
use ApiSutra\Tests\Stubs\HydrationRules\RecordDto;
use ApiSutra\Tests\Stubs\HydrationRules\ReturnCast;
use ApiSutra\Tests\Stubs\HydrationRules\ScalarDto;
use ApiSutra\Tests\Stubs\HydrationRules\ScopedChildCast;
use ApiSutra\Tests\Stubs\HydrationRules\StringReceiverDto;
use ApiSutra\Tests\Stubs\HydrationRules\ValueDto;
use ApiSutra\Tests\Stubs\HydrationRules\NodeDto;
use ApiSutra\Tests\Stubs\HydrationRules\RequiredReceiverDto;
use ApiSutra\Tests\Stubs\HydrationRules\WireCounter;
use ApiSutra\Tests\Support\HydrationRulesFixture as Fixture;

it('отклоняет повторные декларации и неверные ссылки до данных', function (string $case): void {
    $operation = match ($case) {
        'class' => fn () => HydrationRules::create()->withDto(ValueDto::class, DtoRules::create())->withDto(ValueDto::class, DtoRules::create()),
        'field' => fn () => DtoRules::create()->field('value', FieldRule::create())->field('value', FieldRule::create()),
        'receiver' => fn () => DtoRules::create()->extras('extra')->extras('value'),
        'mapping' => fn () => FieldRule::create()->from('a')->from('b'),
        'default' => fn () => FieldRule::create()->default(DefaultSpec::value(null))->default(DefaultSpec::value(1)),
        'policy' => fn () => FieldRule::create()->policy(new RulePolicy())->policy(new RulePolicy()),
        'transform' => fn () => FieldRule::create()->noTransform()->shape(ValueShape::mixed()),
        'casts' => fn () => FieldRule::create()->policy(new RulePolicy(casts: ['int' => new HandlerSpec(ReturnCast::class, [1])])),
        'unknown-class' => fn () => Hydrator::forRules(HydrationRules::create()->withDto('UnknownRuleClass', DtoRules::create())),
        'unknown-field' => fn () => Hydrator::forRules(HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()->field('unknown', FieldRule::create()))),
        'empty-path' => fn () => Hydrator::forRules(HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()->from('')))),
        'handler' => fn () => Hydrator::forRules(HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()->cast(new HandlerSpec(stdClass::class))))),
        'variants' => fn () => Hydrator::forRules(HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()->shape(ValueShape::variants('type', ['known' => RecordDto::class]))))),
    };
    expect($operation)->toThrow(ConfigurationException::class);
})->with(['class', 'field', 'receiver', 'mapping', 'default', 'policy', 'transform', 'casts', 'unknown-class', 'unknown-field', 'empty-path', 'handler', 'variants']);

it('отклоняет непригодный receiver и собственное представление', function (string $class, string $field): void {
    expect(fn () => Hydrator::forRules(HydrationRules::create()->withDto($class, DtoRules::create()->extras($field))))
        ->toThrow(ConfigurationException::class);
})->with([
    [InvalidReceiversDto::class, 'static'], [InvalidReceiversDto::class, 'virtual'],
    [InvalidReceiversDto::class, 'protected'], [InvalidReceiversDto::class, 'wrong'], [InvalidReceiversDto::class, 'extra'],
    [DateReceiverDto::class, 'extra'], [JsonReceiverDto::class, 'extra'], [StringReceiverDto::class, 'extra'], [ArrayReceiverDto::class, 'extra'],
]);

it('отклоняет FieldRule receiver и классовый DtoHydrate', function (): void {
    expect(fn () => Hydrator::forRules(HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()
        ->extras('extra')->field('extra', FieldRule::create()))))->toThrow(ConfigurationException::class)
        ->and(fn () => Hydrator::forRules(HydrationRules::create()->withDto(HydratePolicyDto::class, DtoRules::create())))
        ->toThrow(ConfigurationException::class);
});

it('не конфликтует с исходящими и посторонними атрибутами и сохраняет соседний From', function (): void {
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))->withDto(
        OutputAttributesDto::class,
        DtoRules::create()->field('label', FieldRule::create()->from('title'))
        ->field('date', FieldRule::create())->field('other', FieldRule::create())
    );
    $dto = Hydrator::forRules($rules)->hydrate(['source' => 7, 'title' => 'label', 'date' => '2026-01-01T00:00:00+00:00'], OutputAttributesDto::class);
    expect($dto->id)->toBe(7)->and($dto->label)->toBe('label')->and($dto->date)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($dto->other)->toBe('default');
});

it('передаёт scope в casts набора и класса и сохраняет приоритеты policy', function (bool $classCast): void {
    $casts = [RecordDto::class => new HandlerSpec(ScopedChildCast::class)];
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict, casts: $classCast ? [] : $casts))
        ->withDto(PlainChildDto::class, DtoRules::create(new RulePolicy(scalars: ScalarPolicy::Legacy, casts: $classCast ? $casts : [])))
        ->withDto(RecordDto::class, DtoRules::create()->field('id', FieldRule::create()->from('record_id')));
    expect(Hydrator::forRules($rules)->hydrate(['child' => ['record_id' => 7]], PlainChildDto::class)->child->id)->toBe(7);
    expect(Fixture::error(fn () => Hydrator::forRules($rules)->hydrate(['child' => ['record_id' => '7']], PlainChildDto::class))->path)->toBe('child.id');
    $plain = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict));
    expect(Fixture::error(fn () => Hydrator::forRules($plain)->hydrate(['child' => ['id' => 7]], PlainChildDto::class))->reason)
        ->toBe('invalid_field_type');
})->with([false, true]);

it('различает явные Legacy, noTransform, default null и сохранение исходного fallback', function (): void {
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict, casts: ['int' => new HandlerSpec(ReturnCast::class, [99])]))
        ->withDto(ScalarDto::class, DtoRules::create(new RulePolicy(casts: ['int' => new HandlerSpec(ReturnCast::class, [88])]))
            ->field('int', FieldRule::create()->noTransform()->policy(new RulePolicy(scalars: ScalarPolicy::Legacy))));
    expect(Hydrator::forRules($rules)->hydrate(['int' => '7'], ScalarDto::class)->int)->toBe(7);
    $rules = HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()
        ->from('a', 'b')->default(DefaultSpec::value(null))));
    $hydrator = Hydrator::forRules($rules);
    expect($hydrator->hydrate([], ValueDto::class)->value)->toBeNull()
        ->and($hydrator->hydrate(['a' => false, 'b' => true], ValueDto::class)->value)->toBeFalse()
        ->and($hydrator->hydrate(['a' => null, 'b' => true], ValueDto::class)->value)->toBeNull();
});

it('соблюдает normalization Keep и NullIfEmpty, атрибут и исключение Cast', function (EmptyStringBehavior $empty, string $state): void {
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict, emptyString: $empty));
    $input = $state === 'missing' ? [] : array_fill_keys(['plain', 'empty', 'cast'], $state === 'null' ? null : '');
    $dto = Hydrator::forRules($rules)->hydrate($input, NormalizerDto::class);
    expect($dto->empty)->toBeNull()
        ->and($dto->plain)->toBe($state === 'empty' && $empty === EmptyStringBehavior::Keep ? '' : null)
        ->and($dto->cast)->toBe($state === 'empty' ? 'cast' : null);
})->with([EmptyStringBehavior::Keep, EmptyStringBehavior::NullIfEmpty])->with(['missing', 'null', 'empty']);

it('обрабатывает typed collection, required до defaults и запрещает KeepRaw', function (): void {
    $field = FieldRule::create()->shape(ValueShape::list(ValueShape::dto(RecordDto::class)));
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
        ->withDto(CollectionDto::class, DtoRules::create()->field('items', $field));
    expect(Hydrator::forRules($rules)->hydrate(['items' => [['id' => 7]]], CollectionDto::class)->items->all()[0]->id)->toBe(7)
        ->and(Hydrator::forRules($rules)->hydrate([], CollectionDto::class)->items->all())->toBe([]);
    $required = HydrationRules::create()->withDto(CollectionDto::class, DtoRules::create()->field('items', $field->required()));
    expect(Fixture::error(fn () => Hydrator::forRules($required)->hydrate([], CollectionDto::class))->reason)->toBe('required_field_missing');
    $raw = HydrationRules::create()->withDto(CollectionDto::class, DtoRules::create()->field('items', FieldRule::create()
        ->shape(ValueShape::list(ValueShape::variants('type', ['known' => RecordDto::class])))));
    expect(fn () => Hydrator::forRules($raw))->toThrow(ConfigurationException::class);
});

it('проверяет object-форму и передаёт обязательный receiver в единственный конструктор', function (): void {
    $rules = HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()
        ->field('value', FieldRule::create()->shape(ValueShape::dto(NodeDto::class))));
    $hydrator = Hydrator::forRules($rules);
    expect($hydrator->hydrate(['value' => []], ValueDto::class)->value)->toBeInstanceOf(NodeDto::class)
        ->and(Fixture::error(fn () => $hydrator->hydrate(['value' => [1]], ValueDto::class))->reason)->toBe('invalid_object_shape');
    WireCounter::$constructed = 0;
    $rules = HydrationRules::create()->withDto(RequiredReceiverDto::class, DtoRules::create()->extras('extra'));
    $dto = Hydrator::forRules($rules)->hydrate(['id' => 7, 'future' => false], RequiredReceiverDto::class);
    expect($dto->extra)->toBe(['future' => false])->and(WireCounter::$constructed)->toBe(1);
});
