<?php

declare(strict_types=1);

use ApiSutra\Config\HydrationConfig;
use ApiSutra\Tests\Stubs\AttributeHydration\ArrayDto as AttributeArrayDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\ValueHandler;
use ApiSutra\Serialization\Rules\HandlerSpec;
use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DefaultSpec;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\SourcePathKind;
use ApiSutra\Serialization\Rules\ValueShape;
use ApiSutra\Tests\Stubs\ConstructorOwned\ArrayDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\State;
use ApiSutra\Tests\Support\ConstructorOwnedFixture as Fixture;
use ApiSutra\Tests\Support\HydrationRulesFixture;

beforeEach(function (): void {
    State::$calls = 0;
    State::$value = [];
});

it('сравнивает рекурсивные массивы по ключам без перестановки входа', function (array $expected, array $input, bool $equal, bool $attributes): void {
    State::$value = $expected;
    $before = $input;
    $hydrator = $attributes ? Hydrator::default() : Hydrator::forRules(Fixture::rules());
    $class = $attributes ? AttributeArrayDto::class : ArrayDto::class;
    if ($equal) {
        expect($hydrator->hydrate(['value' => $input], $class)->value)->toBe($expected);
    } else {
        $error = HydrationRulesFixture::error(fn () => $hydrator->hydrate(['value' => $input], $class));
        expect($error->reason)->toBe('constructor_value_mismatch')->and($error->path)->toBe('value')
            ->and($error->sourcePath)->toBe('/value');
    }
    expect($input)->toBe($before)->and(State::$calls)->toBe(1);
})->with([
    [[], [], true], [[1, null, ValueState::Present], [1, null, ValueState::Present], true],
    [[1, 2], [2, 1], false], [[1], ['1'], false], [[10], [10.0], false], [[1], [], false],
    [['a' => ['x' => 1, 'y' => null], 'b' => [false]], ['b' => [false], 'a' => ['y' => null, 'x' => 1]], true],
    [['a' => null], [], false], [[1 => 'b', 0 => 'a'], ['a', 'b'], true],
    [[1 => 'b', '01' => 'c'], ['01' => 'c', 1 => 'b'], true], [[2 => 'a'], [0 => 'a'], false],
    [[ValueState::Present], [ValueState::Null], false], [[ValueState::Present], ['present'], false],
    [[NAN], [NAN], false], [json_decode('{}', true), json_decode('[]', true), true],
])->with([false, true]);

it('проверяет полный допустимый домен до сравнения, без вызова пользовательских методов', function (string $kind, bool $constructor): void {
    $invalid = match ($kind) {
        'dto' => new class {
            public function __toString(): string
            {
                throw new LogicException('Метод объекта не должен вызываться');
            }
        },
        'date' => new DateTimeImmutable(),
        'object' => new stdClass(),
        'closure' => static fn (): int => 1,
        'resource' => fopen('php://memory', 'r+'),
    };
    $bad = ['first' => false, 'secret-key' => [$invalid]];
    State::$value = $constructor ? $bad : [];
    try {
        $hydrate = fn () => Hydrator::forRules(Fixture::rules())->hydrate(['value' => $constructor ? [] : $bad], ArrayDto::class);
        if ($constructor) {
            expect($hydrate)->toThrow(ConfigurationException::class);
        } else {
            $error = HydrationRulesFixture::error($hydrate);
            expect($error->reason)->toBe('invalid_field_type')->and($error->path)->toBe('value')
                ->and($error->sourcePath)->toBe('/value')->and($error->getMessage())->not->toContain('secret-key');
        }
        expect(State::$calls)->toBe($constructor ? 1 : 0);
        if ($constructor) {
            $rules = HydrationRules::create()->withDto(ArrayDto::class, DtoRules::create()
                ->field('value', FieldRule::create()->constructorValue(true)));
            expect(fn () => Hydrator::forRules($rules)->hydrate([], ArrayDto::class))->toThrow(ConfigurationException::class);
        }
    } finally {
        if (is_resource($invalid)) {
            fclose($invalid);
        }
    }
})->with(['dto', 'date', 'object', 'closure', 'resource'])->with([false, true]);

it('считает array-контейнеры включая пустой последний и ограничивает циклы', function (int $depth, bool $constructor): void {
    $value = Fixture::deep($depth);
    State::$value = $constructor ? $value : [];
    $hydrator = Hydrator::forRules(Fixture::rules());
    if ($depth <= 512) {
        State::$value = $value;
        expect($hydrator->hydrate(['value' => $value], ArrayDto::class)->value)->toBe($value);
    } elseif ($constructor) {
        expect(fn () => $hydrator->hydrate(['value' => []], ArrayDto::class))->toThrow(ConfigurationException::class);
    } else {
        $error = HydrationRulesFixture::error(fn () => $hydrator->hydrate(['value' => $value], ArrayDto::class));
        expect($error->reason)->toBe('hydration_depth_exceeded')->and($error->sourcePath)->toBe('/value');
    }
    expect(State::$calls)->toBe($depth <= 512 || $constructor ? 1 : 0);
})->with([511, 512, 513])->with([false, true]);

it('останавливает цикл, но разрешает повтор конечной ветви', function (bool $constructor): void {
    $cycle = [];
    $cycle['loop'] = &$cycle;
    State::$value = $constructor ? $cycle : [];
    $hydrator = Hydrator::forRules(Fixture::rules());
    if ($constructor) {
        expect(fn () => $hydrator->hydrate(['value' => []], ArrayDto::class))->toThrow(ConfigurationException::class);
    } else {
        $error = HydrationRulesFixture::error(fn () => $hydrator->hydrate(['value' => $cycle], ArrayDto::class));
        expect($error->reason)->toBe('hydration_depth_exceeded')->and(State::$calls)->toBe(0);
    }
    $part = ['a' => [1, null]];
    State::$value = [$part, $part];
    expect($hydrator->hydrate(['value' => [&$part, &$part]], ArrayDto::class)->value)->toBe([$part, $part]);
})->with([false, true]);

it('применяет list/each и default перед сравнением и сохраняет целый путь свойства', function (): void {
    State::$value = [1, 2];
    $rules = Fixture::rules(field: FieldRule::create()->from('primary', 'rows')
        ->shape(ValueShape::list(ValueShape::int(), each: 'number', normalizeKeys: true)));
    $hydrator = Hydrator::forRules($rules);
    expect($hydrator->hydrate(['rows' => [4 => ['number' => 1], 9 => ['number' => 2]]], ArrayDto::class)->value)->toBe([1, 2]);
    $error = HydrationRulesFixture::error(fn () => $hydrator->hydrate(['rows' => [4 => ['number' => 3]]], ArrayDto::class));
    expect($error->reason)->toBe('constructor_value_mismatch')->and($error->path)->toBe('value')->and($error->sourcePath)->toBe('/rows');
    $error = HydrationRulesFixture::error(fn () => $hydrator->hydrate(['rows' => [4 => ['number' => '3']]], ArrayDto::class));
    expect($error->reason)->toBe('invalid_field_type')->and($error->path)->toBe('value[0]')->and($error->sourcePath)->toBe('/rows/4/number');
    $rules = Fixture::rules(field: FieldRule::create()->default(DefaultSpec::value([2, 1])));
    $error = HydrationRulesFixture::error(fn () => Hydrator::forRules($rules)->hydrate([], ArrayDto::class));
    expect($error->reason)->toBe('constructor_value_mismatch')->and($error->sourcePathKind)->toBe(SourcePathKind::Boundary);
});

it('не переносит результаты между узлами, наборами и cold/warm/cache-off', function (bool $cache): void {
    $hydrator = new Hydrator(new CastRegistry(), $cache ? new AttributeMetadataCache() : null, config: new HydrationConfig(rules: Fixture::rules()));
    $wide = array_fill_keys(range(1, 2000), ['x' => 1, 'y' => null]);
    for ($i = 0; $i < 2; $i++) {
        State::$value = $wide;
        expect($hydrator->hydrate(['value' => array_reverse($wide, true)], ArrayDto::class)->value)->toBe($wide);
        State::$value = [];
        expect(HydrationRulesFixture::error(fn () => $hydrator->hydrate(['value' => $wide], ArrayDto::class))->reason)
            ->toBe('constructor_value_mismatch');
        expect(HydrationRulesFixture::error(fn () => $hydrator->hydrate([], ArrayDto::class))->reason)->toBe('required_field_missing');
    }
    $other = Hydrator::forRules(Fixture::rules(field: FieldRule::create()->default(DefaultSpec::value([]))));
    expect($other->hydrate([], ArrayDto::class)->value)->toBe([])->and(State::$calls)->toBe(5);
})->with([false, true]);

it('не раскрывает внутренний секретный ключ при mismatch преобразованного массива', function (): void {
    State::$value = ['secret-key' => 'expected'];
    $field = FieldRule::create()->from('payload')
        ->cast(new HandlerSpec(ValueHandler::class));
    $error = HydrationRulesFixture::error(fn () => Hydrator::forRules(Fixture::rules(field: $field))
        ->hydrate(['payload' => ['secret-key' => 'synthetic-secret']], ArrayDto::class));
    expect($error->reason)->toBe('constructor_value_mismatch')->and($error->sourcePath)->toBe('/payload')
        ->and($error->sourcePathKind)->toBe(SourcePathKind::Boundary)
        ->and(json_encode($error->logContext(), JSON_THROW_ON_ERROR))->not->toContain('secret-key', 'synthetic-secret');
});
