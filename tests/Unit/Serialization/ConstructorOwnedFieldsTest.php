<?php

declare(strict_types=1);

use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DefaultSpec;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\HandlerSpec;
use ApiSutra\Tests\Stubs\ConstructorOwned\ValueHandler;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Serialization\Rules\SourcePathKind;
use ApiSutra\Tests\Stubs\ConstructorOwned\MutableDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\State;
use ApiSutra\Tests\Stubs\ConstructorOwned\StringDto;
use ApiSutra\Tests\Support\HydrationRulesFixture;

beforeEach(function (): void {
    State::$calls = State::$handlers = 0;
    State::$value = 'known';
});

it('проверяет readonly и mutable поле после одного конструктора, затем заполняет обычное поле', function (string $class): void {
    $rules = HydrationRules::create()->withDto($class, DtoRules::create()->field('value', FieldRule::create()->constructorValue()));
    $dto = Hydrator::forRules($rules)->hydrate(['value' => 'known', 'id' => 7], $class);
    expect($dto->value)->toBe('known')->and(State::$calls)->toBe(1);
    if ($dto instanceof MutableDto) {
        expect($dto->id)->toBe(7);
    }
})->with([StringDto::class, MutableDto::class]);

it('различает mismatch, missing и ошибку типа, сохраняя путь и число конструкторов', function (array $input, string $reason, int $calls): void {
    $rules = HydrationRules::create()->withDto(StringDto::class, DtoRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
        ->field('value', FieldRule::create()->from('payload.kind', 'legacy')->constructorValue()));
    $error = HydrationRulesFixture::error(fn () => Hydrator::forRules($rules)->hydrate($input, StringDto::class));
    expect($error)->toBeInstanceOf(HydrationException::class)->and($error->reason)->toBe($reason)
        ->and($error->path)->toBe('value')->and(State::$calls)->toBe($calls)
        ->and($error->getMessage())->not->toContain('secret-value');
    if ($input !== []) {
        expect($error->sourcePath)->toBe('/legacy');
    } else {
        expect($error->sourcePathKind)->toBe(SourcePathKind::Expected)
            ->and($error->sourceCandidates)->toBe(['/payload/kind', '/legacy']);
    }
})->with([
    [['legacy' => 'secret-value'], 'constructor_value_mismatch', 1],
    [[], 'required_field_missing', 0],
    [['legacy' => 1], 'invalid_field_type', 0],
    [['legacy' => false], 'invalid_field_type', 0],
    [['legacy' => []], 'invalid_field_type', 0],
    [['legacy' => null], 'null_not_allowed', 0],
]);

it('allowMissing не отменяет required, default и запрет явного null', function (): void {
    $hydrate = static fn (FieldRule $field, array $data): object => Hydrator::forRules(HydrationRules::create()
        ->withDto(StringDto::class, DtoRules::create()->field('value', $field)))->hydrate($data, StringDto::class);
    expect($hydrate(FieldRule::create()->constructorValue(allowMissing: true), [])->value)->toBe('known');
    State::$calls = 0;
    foreach (
        [
        [FieldRule::create()->constructorValue(true)->required()->default(DefaultSpec::value('known')), []],
        [FieldRule::create()->constructorValue(true)->forbidExplicitNull(), ['value' => null]],
        ] as [$field, $input]
    ) {
        expect(fn () => $hydrate($field, $input))->toThrow(HydrationException::class);
    }
    expect(State::$calls)->toBe(0);
    expect($hydrate(FieldRule::create()->constructorValue()->default(DefaultSpec::value('known')), [])->value)->toBe('known');
    $error = HydrationRulesFixture::error(fn () => $hydrate(FieldRule::create()->constructorValue()->default(DefaultSpec::value('other')), []));
    expect($error->reason)->toBe('constructor_value_mismatch')->and($error->sourcePathKind)->toBe(SourcePathKind::Boundary);
});

it('Legacy сравнивает итог обычного Hydrator, включая noTransform и канонические union', function (string $suffix, mixed $input, mixed $normal, mixed $raw): void {
    $class = 'ApiSutra\\Tests\\Stubs\\ConstructorOwned\\' . $suffix . 'Dto';
    $writable = 'ApiSutra\\Tests\\Stubs\\ConstructorOwned\\Writable' . $suffix . 'Dto';
    foreach ([false, true, 'cast'] as $noTransform) {
        $expected = $noTransform ? $raw : $normal;
        State::$value = $expected;
        $field = match ($noTransform) {
            'cast' => FieldRule::create()->cast(new HandlerSpec(ValueHandler::class)),
            true => FieldRule::create()->noTransform(),
            default => FieldRule::create(),
        };
        $rules = HydrationRules::create()
            ->withDto($class, DtoRules::create()->field('value', $field->constructorValue()))
            ->withDto($writable, DtoRules::create()->field('value', $field));
        $hydrator = Hydrator::forRules($rules);
        $ordinary = $hydrator->hydrate(['value' => $input], $writable);
        $owned = $hydrator->hydrate(['value' => $input], $class);
        expect($ordinary->value)->toBe($expected)->and($owned->value)->toBe($expected)->toBe($ordinary->value);
    }
    expect(State::$calls)->toBe(3)->and(State::$handlers)->toBe(2);
})->with([
    ['FloatString', 5, '5', 5.0], ['StringFloat', 5, '5', 5.0], ['BoolInt', 'false', false, true],
    ['Int', '7', 7, 7], ['String', true, '1', '1'], ['IntBool', '1', 1, 1],
]);

it('сохраняет запрет повторной записи без opt-in и не разрешает повторную группу', function (): void {
    expect(fn () => Hydrator::default()->hydrate(['value' => 'known'], StringDto::class))->toThrow(ConfigurationException::class);
    expect(fn () => FieldRule::create()->constructorValue()->constructorValue(true))->toThrow(ConfigurationException::class);
});
