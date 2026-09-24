<?php

declare(strict_types=1);

use ApiSutra\Serialization\Rules\SourcePathKind;
use ApiSutra\Tests\Stubs\AttributeHydration\ScopedRows;
use ApiSutra\Tests\Stubs\HydrationRules\ScopedAttributeDto;
use ApiSutra\Tests\Stubs\AttributeHydration\FloatStringDto;
use ApiSutra\Tests\Stubs\AttributeHydration\BoolIntDto;
use ApiSutra\Tests\Stubs\AttributeHydration\EnumDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\WritableFloatStringDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\WritableBoolIntDto;
use ApiSutra\Tests\Stubs\ConstructorOwned\Kind;
use ApiSutra\Tests\Stubs\ConstructorOwned\State;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Serialization\Rules\ValueShape;
use ApiSutra\Tests\Stubs\AttributeHydration\Record;
use ApiSutra\Tests\Stubs\AttributeHydration\PlainRecord;
use ApiSutra\Tests\Stubs\AttributeHydration\RecordRequest;
use ApiSutra\Tests\Stubs\AttributeHydration\Envelope;
use ApiSutra\Tests\Stubs\AttributeHydration\NullableRows;
use ApiSutra\Tests\Stubs\AttributeHydration\NormalizedRows;
use ApiSutra\Tests\Stubs\AttributeHydration\VariantRows;
use ApiSutra\Tests\Stubs\AttributeHydration\Presence;
use ApiSutra\Tests\Stubs\AttributeHydration\ConstructorDefaults;
use ApiSutra\Tests\Stubs\AttributeHydration\LegacyParent;
use ApiSutra\Tests\Stubs\AttributeHydration\Row;
use ApiSutra\Tests\Support\HydrationRulesFixture as Fixture;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;

it('гидратирует точный Record из AH-1–AH-5 без внешнего реестра', function (bool $http): void {
    $payload = ['kind' => 'record', 'record_id' => 7, 'rows' => [[1, 2], []], 'stock' => 0, 'future' => false, 'extra' => null];
    $config = new HydrationConfig(policy: new RulePolicy(scalars: ScalarPolicy::Strict));
    if ($http) {
        $transport = new MockTransport();
        $transport->fake(['*' => MockResponse::success(['data' => $payload])]);
        $dto = (new TestClient(new ClientConfig(baseUrl: 'https://attributes.test', hydration: $config), $transport))
            ->send(new RecordRequest())->dataOrFail();
    } else {
        $dto = Hydrator::forConfig($config)->hydrate($payload, Record::class);
    }
    expect(get_object_vars($dto))->toBe(['kind' => 'record', 'id' => 7, 'rows' => [[1, 2], []], 'stock' => 0,
        'extra' => ['future' => false, 'extra' => null]]);
})->with([false, true]);

it('даёт равные результаты и ошибки атрибутного и внешнего объявления', function (array $changes, ?string $reason, ?string $path): void {
    $policy = new RulePolicy(scalars: ScalarPolicy::Strict);
    $rules = HydrationRules::create()->withDto(PlainRecord::class, DtoRules::create()
        ->field('kind', FieldRule::create()->constructorValue())
        ->field('id', FieldRule::create()->from('record_id')->required())
        ->field('rows', FieldRule::create()->shape(ValueShape::list(ValueShape::list(ValueShape::int()))))
        ->field('stock', FieldRule::create()->forbidExplicitNull())->extras('extra'));
    $payload = array_replace(['kind' => 'record', 'record_id' => 7, 'rows' => [[1], []], 'future' => false], $changes);
    $results = [];
    foreach ([Record::class => null, PlainRecord::class => $rules] as $class => $external) {
        $hydrator = Hydrator::forConfig(new HydrationConfig(policy: $policy, rules: $external));
        if ($reason === null) {
            $results[] = get_object_vars($hydrator->hydrate($payload, $class));
        } else {
            $error = Fixture::error(fn () => $hydrator->hydrate($payload, $class));
            expect($error->reason)->toBe($reason)->and($error->path)->toBe($path);
            $results[] = [$error->reason, $error->path, $error->sourcePath, $error->sourcePathKind];
        }
    }
    expect($results[0])->toBe($results[1]);
})->with([
    [[], null, null], [['stock' => 0], null, null],
    [['stock' => null], 'explicit_null_not_allowed', 'stock'],
    [['record_id' => '7'], 'invalid_field_type', 'id'],
    [['kind' => 'other'], 'constructor_value_mismatch', 'kind'],
    [['rows' => ['key' => [1]]], 'invalid_list_shape', 'rows'],
    [['rows' => [[1, 'bad']]], 'invalid_field_type', 'rows[0][1]'],
]);

it('сохраняет fallback each вложенный receiver и значения остатка', function (): void {
    $payload = ['legacy' => [['value' => ['id' => 7, 'unknown' => null], 'meta' => false]],
        'child' => ['id' => 8, 'future' => []], 'zero' => 0, 'empty' => '', '_extra' => null];
    $dto = Hydrator::default()->hydrate($payload, Envelope::class);
    expect($dto->items[0]->id)->toBe(7)->and($dto->items[0]->_extra)->toBe(['unknown' => null])
        ->and($dto->child->_extra)->toBe(['future' => []])
        ->and($dto->_extra)->toBe(['legacy' => [['sourceKey' => 0, 'remainder' => ['meta' => false]]],
            'zero' => 0, 'empty' => '', '_extra' => null]);
});

it('различает nullable значения и элементы и нормализует ключи только явно', function (): void {
    foreach ([null, [], [1, null]] as $value) {
        expect(Hydrator::default()->hydrate(['values' => $value], NullableRows::class)->values)->toBe($value);
    }
    expect(Fixture::error(fn () => Hydrator::default()->hydrate(['values' => [2 => 7]], NullableRows::class))->reason)
        ->toBe('invalid_list_shape');
    expect(Hydrator::default()->hydrate(['values' => [2 => 7]], NormalizedRows::class)->values)->toBe([7]);
});

it('проверяет исходное присутствие и null раньше default provider', function (): void {
    expect(Fixture::error(fn () => Hydrator::default()->hydrate([], Presence::class))->reason)->toBe('required_field_missing');
    expect(Fixture::error(fn () => Hydrator::default()->hydrate(['current' => 7, 'stock' => null], Presence::class))->reason)
        ->toBe('explicit_null_not_allowed');
    expect(Fixture::error(fn () => Hydrator::default()->hydrate(['current' => null, 'old' => 7], Presence::class))->reason)
        ->toBe('null_not_allowed');
    expect(Hydrator::default()->hydrate(['old' => 7], Presence::class)->id)->toBe(7)
        ->and(Hydrator::default()->hydrate([], ConstructorDefaults::class)->kind)->toBe('record');
});

it('сохраняет путь нового ребёнка под старым Nested родителя', function (): void {
    $error = Fixture::error(fn () => Hydrator::default()->hydrate(['child' => []], LegacyParent::class));
    expect($error->path)->toBe('child.id')->and($error->sourcePath)->toBe('/child/id');
    expect(Row::from(['id' => 7, 'future' => false])->_extra)->toBe(['future' => false]);
});

it('переводит discriminator в существующий ValueShape и сохраняет KeepRaw', function (): void {
    $dto = Hydrator::default()->hydrate(['values' => [['type' => 'row', 'id' => 7], ['type' => 'future']]], VariantRows::class);
    expect($dto->values[0])->toBeInstanceOf(Row::class)->and($dto->values[0]->_extra)->toBe(['type' => 'row'])
        ->and($dto->values[1])->toBe(['type' => 'future']);
});

it('передаёт общую политику в атрибутные cast provider и itemCast внутреннего списка', function (bool $strict): void {
    $policy = new RulePolicy(scalars: $strict ? ScalarPolicy::Strict : ScalarPolicy::Legacy);
    $hydrator = Hydrator::forConfig(new HydrationConfig(policy: $policy));
    $scenarios = [
        [ScopedRows::class, ['rows' => [[['id' => '7']]]], 'rows[0][0].id'],
        [ScopedAttributeDto::class, ['child' => ['id' => '7'], 'rows' => [], 'provided' => ['id' => 7]], 'child.id'],
        [ScopedAttributeDto::class, ['child' => ['id' => 7], 'rows' => [[['id' => '7']]], 'provided' => ['id' => 7]], 'rows[0][0].id'],
        [ScopedAttributeDto::class, ['child' => ['id' => 7], 'rows' => [], 'provided' => ['id' => '7']], 'provided.id'],
    ];
    foreach ($scenarios as [$class, $input, $path]) {
        if ($strict) {
            $error = Fixture::error(fn () => $hydrator->hydrate($input, $class));
            expect($error->reason)->toBe('invalid_field_type')->and($error->path)->toBe($path)
                ->and($error->sourcePathKind)->toBe(SourcePathKind::Boundary);
        } else {
            expect($hydrator->hydrate($input, $class))->toBeInstanceOf($class);
        }
    }
})->with([false, true]);

it('проверяет атрибутный constructorValue enum и Legacy union по обычному полю', function (): void {
    foreach (
        [[FloatStringDto::class, WritableFloatStringDto::class, 5, '5'],
        [BoolIntDto::class, WritableBoolIntDto::class, 'false', false]] as [$owned, $ordinary, $input, $expected]
    ) {
        State::$calls = 0;
        State::$value = $expected;
        $hydrator = Hydrator::default();
        expect($hydrator->hydrate(['value' => $input], $ordinary)->value)->toBe($expected)
            ->and($hydrator->hydrate(['value' => $input], $owned)->value)->toBe($expected)->and(State::$calls)->toBe(1);
    }
    State::$calls = 0;
    expect(Hydrator::default()->hydrate(['value' => Kind::Known->value], EnumDto::class)->value)->toBe(Kind::Known)
        ->and(State::$calls)->toBe(1);
});
