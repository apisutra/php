<?php

declare(strict_types=1);

use ApiSutra\Serialization\Rules\HandlerSpec;
use ApiSutra\Tests\Stubs\HydrationRules\RecordDto;
use ApiSutra\Tests\Stubs\HydrationRules\ReturnCast;
use ApiSutra\Tests\Stubs\HydrationRules\ProfiledDto;
use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Tests\Stubs\MetadataIsolation\CreatedValue;
use ApiSutra\Tests\Stubs\MetadataIsolation\CreatedCastDto;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Enums\DataTransfer\EmptyStringBehavior;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Serializer;
use ApiSutra\Serialization\DtoSerializer;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\RuleSetCompiler;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Serialization\Rules\ScalarType;
use ApiSutra\Serialization\Rules\ValueShape;
use ApiSutra\Serialization\Shapes\ShapeCompiler;
use ApiSutra\Serialization\Shapes\ShapeSpec;
use ApiSutra\Serialization\Shapes\ListShape;
use ApiSutra\Serialization\Shapes\NullableShape;
use ApiSutra\Serialization\Shapes\DtoShape;
use ApiSutra\Serialization\Shapes\VariantsShape;
use ApiSutra\Tests\Stubs\AttributeHydration as Dto;
use ApiSutra\Tests\Support\HydrationRulesFixture as Fixture;

it('отклоняет конфликтные и неприменимые атрибуты до исполнения модели', function (string $class): void {
    expect(fn () => Hydrator::default()->hydrate([], $class))->toThrow(ConfigurationException::class);
})->with([
    Dto\Repeated::class, Dto\ShapeCast::class, Dto\ShapeNested::class, Dto\TwoReceivers::class,
    Dto\ReceiverInput::class, Dto\ReceiverOutput::class, Dto\StaticField::class,
    Dto\PromotedConstructor::class, Dto\MissingChild::class, Dto\TwoInheritedReceivers::class,
    Dto\JsonReceiverDto::class, Dto\StringReceiverDto::class, Dto\ArrayReceiverDto::class,
]);

it('различает конфликт того же поля и допустимые соседние декларации', function (): void {
    foreach (['id', 'rows', 'kind', 'stock', 'extra'] as $field) {
        $rules = HydrationRules::create()->withDto(Dto\Record::class, DtoRules::create()->field($field, FieldRule::create()));
        expect(fn () => Hydrator::forRules($rules))->toThrow(ConfigurationException::class);
    }
    $rules = HydrationRules::create()->withDto(Dto\Row::class, DtoRules::create()->extras('_extra'));
    expect(fn () => Hydrator::forRules($rules))->toThrow(ConfigurationException::class);
    $rules = HydrationRules::create()->withDto(Dto\PlainRecord::class, DtoRules::create()->field('kind', FieldRule::create()->constructorValue()));
    expect(Hydrator::forRules($rules)->hydrate(['kind' => 'record', 'id' => 7], Dto\PlainRecord::class)->id)->toBe(7);
});

it('сохраняет строгую общую policy под naming override и полным старым профилем', function (string $class): void {
    $config = new HydrationConfig(policy: new RulePolicy(scalars: ScalarPolicy::Strict));
    expect(Fixture::error(fn () => Hydrator::forConfig($config)->hydrate(['record_id' => '7'], $class))->reason)->toBe('invalid_field_type');
    expect(Hydrator::forConfig($config)->hydrate(['record_id' => 7], $class)->recordId)->toBe(7);
    // Старые defaults набора по-прежнему исключены на классах с профилем.
    expect(Hydrator::forRules(HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict)))
        ->hydrate(['record_id' => '7'], $class)->recordId)->toBe(7);
})->with([Dto\StrictNamed::class, Dto\Profiled::class]);

it('накладывает sparse DtoHydrate и сохраняет полные значения профиля', function (): void {
    $config = new HydrationConfig(policy: new RulePolicy(
        scalars: ScalarPolicy::Strict,
        emptyString: EmptyStringBehavior::NullIfEmpty,
        naming: NamingStrategy::None
    ));
    expect(Hydrator::forConfig($config)->hydrate(['record_id' => '7'], Dto\LegacyNamed::class)->recordId)->toBe(7);
    expect(Hydrator::forConfig($config)->hydrate(['record_id' => 7, 'value' => ''], Dto\Profiled::class)->value)->toBe('');
});

it('поддерживает рекурсивные формы и очищает неудачную компиляцию', function (): void {
    $compiler = new RuleSetCompiler();
    for ($i = 0; $i < 2; $i++) {
        expect(fn () => $compiler->forClass(Dto\MissingChild::class))->toThrow(ConfigurationException::class);
    }
    expect(Hydrator::default()->hydrate(['child' => ['child' => null]], Dto\Recursive::class)->child->child)->toBeNull();
});

it('мигрирует named и positional конструкторы с явными ошибками старого API', function (): void {
    $casts = new CastRegistry();
    $cache = new AttributeMetadataCache();
    $rules = HydrationRules::create();
    $config = new HydrationConfig(rules: $rules);
    foreach (
        [new Hydrator($casts, $cache, null, $config), new Hydrator($casts, $cache, config: $config),
        Hydrator::forConfig($config), Hydrator::forRules($rules)] as $hydrator
    ) {
        expect($hydrator->hydrate(['id' => 7], Dto\Row::class)->id)->toBe(7);
    }
    $dto = new Dto\Row(7, ['future' => false]);
    foreach ([new DtoSerializer($casts, $cache, null, $config), new DtoSerializer($casts, $cache, config: $config)] as $serializer) {
        expect($serializer->serialize($dto))->toBe(['id' => 7]);
    }
    foreach (
        [fn () => new Hydrator($casts, rules: $rules), fn () => new Serializer($casts, rules: $rules),
        fn () => new DtoSerializer($casts, rules: $rules), fn () => new ClientConfig(baseUrl: 'https://attributes.test', hydrationRules: $rules)] as $oldNamed
    ) {
        expect($oldNamed)->toThrow(Error::class);
    }
    foreach (
        [fn () => new Hydrator($casts, $cache, null, $rules), fn () => new Serializer($casts, $cache, $rules),
        fn () => new DtoSerializer($casts, $cache, null, $rules)] as $oldPositional
    ) {
        expect($oldPositional)->toThrow(TypeError::class);
    }
    $client = new ClientConfig(baseUrl: 'https://attributes.test', hydration: $config);
    expect($client->with()->hydration)->toBe($config)->and($client->with(hydration: null)->hydration)->toBeNull()
        ->and($client->hydration)->toBe($config);
});

it('классифицирует все фабрики ValueShape без второго словаря семантики', function (): void {
    $forms = [
        'int' => [ScalarType::Int, []], 'float' => [ScalarType::Float, []],
        'bool' => [ScalarType::Bool, []], 'string' => [ScalarType::String, []],
        'list' => [new ListShape(ScalarType::Int), [ValueShape::int()]],
        'nullable' => [new NullableShape(ScalarType::Int), [ValueShape::int()]],
        'dto' => [new DtoShape(Dto\Row::class), [Dto\Row::class]],
        'variants' => [new VariantsShape('type', ['row' => Dto\Row::class]), ['type', ['row' => Dto\Row::class]]],
    ];
    $compiler = new ShapeCompiler();
    foreach ($forms as $factory => [$node, $args]) {
        $kind = in_array($factory, ['int', 'float', 'bool', 'string'], true) ? 'scalar' : $factory;
        expect($compiler->compile($node))->toEqual(ValueShape::$factory(...$args))
            ->and(ValueShape::$factory(...$args)->kind)->toBe($kind);
    }
    $externalOnly = ['mixed' => 'mixed', 'true' => 'scalar', 'false' => 'scalar', 'scalars' => 'scalar'];
    foreach ($externalOnly as $factory => $kind) {
        $args = $factory === 'scalars' ? [ScalarType::Int, ScalarType::String] : [];
        expect(ValueShape::$factory(...$args)->kind)->toBe($kind);
    }
    $factories = array_map(
        static fn (ReflectionMethod $method): string => $method->name,
        array_filter(
            (new ReflectionClass(ValueShape::class))->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $method): bool => $method->isStatic()
        )
    );
    expect($factories)->toEqualCanonicalizing([...array_keys($forms), ...array_keys($externalOnly)]);
    expect(fn () => $compiler->compile(new class implements ShapeSpec {
    }))->toThrow(ConfigurationException::class);
});

it('наследует декларации полей и receiver без повторного promotion', function (): void {
    $dto = Hydrator::default()->hydrate(['id' => 7, 'future' => false], Dto\InheritedRow::class);
    expect($dto->id)->toBe(7)->and($dto->_extra)->toBe(['future' => false]);
});

it('сохраняет профильный cast выше общего и общий cast другого типа', function (): void {
    $config = new HydrationConfig(policy: new RulePolicy(scalars: ScalarPolicy::Strict, casts: [
        RecordDto::class => new HandlerSpec(ReturnCast::class, [false]),
        'int' => new HandlerSpec(ReturnCast::class, [88]),
    ]));
    $dto = Hydrator::forConfig($config)->hydrate(['child' => ['id' => 7], 'count' => 1], ProfiledDto::class);
    expect($dto->child->id)->toBe(88)->and($dto->count)->toBe(88);
});

it('ищет receiver без создания старых аргументов атрибутов и профилей', function (): void {
    CreatedValue::$created = 0;
    $compiler = new RuleSetCompiler();
    foreach ([CreatedCastDto::class, ProfiledDto::class, Dto\PlainRow::class, Dto\DiscoveryDto::class] as $class) {
        $compiler->receiverFor($class);
        $compiler->forClass($class);
        $compiler->receiverFor($class);
    }
    expect(CreatedValue::$created)->toBe(0);
});
