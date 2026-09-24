<?php

declare(strict_types=1);

use ApiSutra\Config\HydrationConfig;
use ApiSutra\Tests\Stubs\ConstructorOwned\ArrayUnionDto;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\DataTransfer\EmptyStringBehavior;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DefaultSpec;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HandlerSpec;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\SourcePathKind;
use ApiSutra\Serialization\Rules\ValueShape;
use ApiSutra\Tests\Stubs\ConstructorOwned\ArrayDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\EnumDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\Flag;
use ApiSutra\Tests\Stubs\ConstructorOwned\FloatDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\InvalidFieldsDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\Kind;
use ApiSutra\Tests\Stubs\ConstructorOwned\NoConstructorDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\NullableDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\State;
use ApiSutra\Tests\Stubs\ConstructorOwned\StringDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\ThrowingDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\TrueDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\FalseDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\UninitializedDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\UnitDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\ValueHandler;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\ConstructorOwnedFixture as Fixture;
use ApiSutra\Tests\Support\HydrationRulesFixture;
use ApiSutra\Transport\MockTransport;

beforeEach(function (): void {
    State::$calls = State::$handlers = 0;
    State::$value = 'known';
});

it('отклоняет неподходящие свойства до HTTP без запуска конструктора', function (string $field): void {
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $rules = HydrationRules::create()->withDto(InvalidFieldsDto::class, DtoRules::create()
        ->field($field, FieldRule::create()->constructorValue()));
    expect(fn () => new TestClient(new ClientConfig(baseUrl: 'https://owned.test', hydration: new HydrationConfig(rules: $rules)), $transport))
        ->toThrow(ConfigurationException::class);
    expect(State::$calls)->toBe(0)->and($transport->getRecorded())->toBe([]);
})->with(['static', 'hidden', 'mixed', 'object', 'untyped', 'defaulted', 'virtual', 'hooked', 'date', 'intersection', 'parameter', 'promoted']);

it('требует конструктор и проверяет форму результата на любой глубине', function (): void {
    expect(fn () => Hydrator::forRules(Fixture::rules(NoConstructorDto::class)))->toThrow(ConfigurationException::class);
    foreach ([ValueShape::dto(StringDto::class), ValueShape::variants('kind', ['known' => StringDto::class])] as $shape) {
        foreach ([$shape, ValueShape::list(ValueShape::nullable(ValueShape::list($shape)))] as $nested) {
            expect(fn () => Hydrator::forRules(Fixture::rules(ArrayDto::class, FieldRule::create()->shape($nested))))
                ->toThrow(ConfigurationException::class);
        }
    }
    expect(State::$calls)->toBe(0);
});

it('сохраняет ошибки конструктора и требует инициализированное поле даже при Missing', function (): void {
    foreach ([false, true] as $missing) {
        $rule = FieldRule::create()->constructorValue($missing);
        $rules = HydrationRules::create()->withDto(UninitializedDto::class, DtoRules::create()->field('value', $rule));
        expect(fn () => Hydrator::forRules($rules)->hydrate($missing ? [] : ['value' => 'known'], UninitializedDto::class))
            ->toThrow(ConfigurationException::class);
    }
    expect(fn () => Hydrator::forRules(Fixture::rules(ThrowingDto::class))->hydrate(['value' => 'known'], ThrowingDto::class))
        ->toThrow(LogicException::class, 'constructor-error');
    expect(State::$calls)->toBe(3);
});

it('применяет provider один раз с Missing и Null и сохраняет Boundary', function (string $class, mixed $value): void {
    State::$value = $value;
    $field = FieldRule::create()->default(DefaultSpec::provider(new HandlerSpec(ValueHandler::class), ValueState::Missing, ValueState::Null));
    $hydrator = Hydrator::forRules(Fixture::rules($class, $field));
    foreach ([[], ['value' => null]] as $source) {
        expect($hydrator->hydrate($source, $class)->value)->toBe($value);
    }
    expect(State::$calls)->toBe(2)->and(State::$handlers)->toBe(2);
    State::$calls = State::$handlers = 0;
    $hydrator = Hydrator::forRules(Fixture::rules($class, $field->forbidExplicitNull()));
    expect(HydrationRulesFixture::error(fn () => $hydrator->hydrate(['value' => null], $class))->reason)->toBe('explicit_null_not_allowed');
    expect(State::$calls)->toBe(0)->and(State::$handlers)->toBe(0);
})->with([[StringDto::class, 'known'], [ArrayDto::class, [1, ['x' => null]]], [NullableDto::class, null]]);

it('сохраняет нормализацию пустой строки и границу cast', function (): void {
    State::$value = null;
    $rule = FieldRule::create()->policy(new RulePolicy(emptyString: EmptyStringBehavior::NullIfEmpty));
    expect(Hydrator::forRules(Fixture::rules(NullableDto::class, $rule))->hydrate(['value' => ''], NullableDto::class)->value)->toBeNull();
    expect(Hydrator::forRules(Fixture::rules(NullableDto::class, $rule->noTransform()))->hydrate(['value' => ''], NullableDto::class)->value)->toBeNull();
    foreach ([FieldRule::create(), $rule->cast(new HandlerSpec(ValueHandler::class))] as $field) {
        $error = HydrationRulesFixture::error(fn () => Hydrator::forRules(Fixture::rules(NullableDto::class, $field))->hydrate(['value' => ''], NullableDto::class));
        expect($error->reason)->toBe('constructor_value_mismatch');
    }
    expect(State::$handlers)->toBe(1);
    State::$value = 'known';
    $field = FieldRule::create()->cast(new HandlerSpec(ValueHandler::class));
    $error = HydrationRulesFixture::error(fn () => Hydrator::forRules(Fixture::rules(StringDto::class, $field))->hydrate(['value' => 'other'], StringDto::class));
    expect($error->sourcePathKind)->toBe(SourcePathKind::Boundary)->and($error->sourcePath)->toBe('/value');
});

it('сравнивает enum cases, literals и точное расширение float', function (string $class, mixed $input, mixed $expected): void {
    State::$value = $expected;
    expect(Hydrator::forRules(Fixture::rules($class, $class === UnitDto::class ? FieldRule::create()->noTransform() : null))->hydrate(['value' => $input], $class)->value)->toBe($expected);
})->with([
    [EnumDto::class, 'known', Kind::Known], [UnitDto::class, Flag::Known, Flag::Known],
    [TrueDto::class, true, true], [FalseDto::class, false, false],
    [FloatDto::class, 9007199254740992, 9007199254740992.0], [NullableDto::class, null, null],
]);

it('не расширяет Strict и различает enum mismatch от неверного enum', function (): void {
    State::$value = 9007199254740992.0;
    $error = HydrationRulesFixture::error(fn () => Hydrator::forRules(Fixture::rules(FloatDto::class))->hydrate(['value' => 9007199254740993], FloatDto::class));
    expect($error->reason)->toBe('invalid_field_type')->and(State::$calls)->toBe(0);
    $hydrator = Hydrator::forRules(Fixture::rules(EnumDto::class));
    expect(HydrationRulesFixture::error(fn () => $hydrator->hydrate(['value' => 'other'], EnumDto::class))->reason)->toBe('constructor_value_mismatch');
    expect(HydrationRulesFixture::error(fn () => $hydrator->hydrate(['value' => 'unknown'], EnumDto::class))->reason)->not->toBe('constructor_value_mismatch');
    expect(State::$calls)->toBe(1);
});

it('отмечает array union без преобразования листьев и NAN как mismatch', function (): void {
    State::$value = ['x' => [1, null]];
    $class = ArrayUnionDto::class;
    expect(Hydrator::forRules(Fixture::rules($class))->hydrate(['value' => ['x' => [1, null]]], $class)->value)->toBe(State::$value);
    State::$value = NAN;
    expect(HydrationRulesFixture::error(fn () => Hydrator::forRules(Fixture::rules(FloatDto::class))->hydrate(['value' => NAN], FloatDto::class))->reason)
        ->toBe('constructor_value_mismatch');
});
