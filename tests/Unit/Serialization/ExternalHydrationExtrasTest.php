<?php

declare(strict_types=1);

use ApiSutra\Enums\DataTransfer\NestedDiscriminatorMode;
use ApiSutra\Enums\DataTransfer\NestedUnknownVariant;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DefaultSpec;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Serialization\Rules\SourcePathKind;
use ApiSutra\Serialization\Rules\ValueShape;
use ApiSutra\Tests\Stubs\HydrationRules\NoConstructorDto;
use ApiSutra\Tests\Stubs\HydrationRules\OwnerDto;
use ApiSutra\Tests\Stubs\HydrationRules\TaggedDto;
use ApiSutra\Tests\Stubs\HydrationRules\TwoValuesDto;
use ApiSutra\Tests\Stubs\HydrationRules\ValueDto;
use ApiSutra\Tests\Support\HydrationRulesFixture;

it('вычитает только выбранный alias и сохраняет исходные пустые и false значения', function (array $source, mixed $value, array $extra): void {
    $rules = HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()
        ->field('value', FieldRule::create()->from('profile.id', 'fallback')->default(DefaultSpec::value(99)))
        ->extras('extra'));
    $dto = Hydrator::forRules($rules)->hydrate($source, ValueDto::class);
    expect($dto->value)->toBe($value)->and($dto->extra)->toBe($extra);
})->with([
    [['profile' => ['id' => 1, 'future' => 0], 'fallback' => 2], 1, ['profile' => ['future' => 0], 'fallback' => 2]],
    [['profile' => ['id' => 1]], 1, []],
    [['profile' => ['id' => null], 'fallback' => 2], null, ['fallback' => 2]],
    [['profile' => ['id' => false], 'future' => []], false, ['future' => []]],
    [['fallback' => 2, 'future' => null, 'blank' => ''], 2, ['future' => null, 'blank' => '']],
    [['profile' => [], 'future' => []], 99, ['profile' => [], 'future' => []]],
    [['profile' => ['id' => 1], 'extra' => ['future' => 1]], 1, ['extra' => ['future' => 1]]],
]);

it('объединяет одинаковые и перекрывающиеся чтения независимо от порядка', function (bool $parentFirst): void {
    $rules = HydrationRules::create()->withDto(TwoValuesDto::class, DtoRules::create()
        ->field('first', FieldRule::create()->from($parentFirst ? 'profile' : 'profile.id'))
        ->field('second', FieldRule::create()->from($parentFirst ? 'profile.id' : 'profile'))->extras('extra'));
    expect(Hydrator::forRules($rules)->hydrate(['profile' => ['id' => 1, 'future' => 2], 'other' => 3], TwoValuesDto::class)->extra)
        ->toBe(['other' => 3]);
    $same = HydrationRules::create()->withDto(TwoValuesDto::class, DtoRules::create()
        ->field('first', FieldRule::create()->from('profile.id'))
        ->field('second', FieldRule::create()->from('profile.id'))->extras('extra'));
    expect(Hydrator::forRules($same)->hydrate(['profile' => ['id' => 1, 'future' => 2]], TwoValuesDto::class)->extra)
        ->toBe(['profile' => ['future' => 2]]);
})->with([false, true]);

it('сохраняет плотное представление остатка each с исходными PHP-ключами', function (array $rows, array $expected): void {
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
        ->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()->from('rows')->shape(
            ValueShape::list(ValueShape::int(), each: 'value', normalizeKeys: true),
        ))->extras('extra'));
    $dto = Hydrator::forRules($rules)->hydrate(['rows' => $rows], ValueDto::class);
    expect($dto->extra)->toBe($expected);
    if ($expected !== []) {
        expect(array_is_list($dto->extra['rows']))->toBeTrue()
            ->and(json_decode(json_encode($dto->extra, JSON_THROW_ON_ERROR), true))->toBe($expected);
    }
})->with([
    [[['value' => 1, 'meta' => 0], ['value' => 2, 'meta' => false]], ['rows' => [
        ['sourceKey' => 0, 'remainder' => ['meta' => 0]], ['sourceKey' => 1, 'remainder' => ['meta' => false]],
    ]]],
    [[['value' => 1], ['value' => 2, 'meta' => 0]], ['rows' => [['sourceKey' => 1, 'remainder' => ['meta' => 0]]]]],
    [[['value' => 1], ['value' => 2]], []],
    [[1 => ['value' => 1, 'meta' => []], '01' => ['value' => 2, 'meta' => '']], ['rows' => [
        ['sourceKey' => 1, 'remainder' => ['meta' => []]], ['sourceKey' => '01', 'remainder' => ['meta' => '']],
    ]]],
]);

it('строит рекурсивные списки остатков для двойной проекции', function (): void {
    $shape = ValueShape::list(ValueShape::list(ValueShape::int(), each: 'value'));
    $rules = HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()
        ->field('value', FieldRule::create()->shape($shape))->extras('extra'));
    $dto = Hydrator::forRules($rules)->hydrate(['value' => [
        [['value' => 1, 'meta' => false], ['value' => 2]],
        [['value' => 3]],
    ]], ValueDto::class);
    expect($dto->value)->toBe([[1, 2], [3]])->and($dto->extra)->toBe(['value' => [
        ['sourceKey' => 0, 'remainder' => [['sourceKey' => 0, 'remainder' => ['meta' => false]]]],
    ]]);
});

it('распределяет discriminator и соседей Key-обёртки по владельцам', function (string $mode): void {
    $type = $mode === 'mapped-value' ? TaggedDto::class : OwnerDto::class;
    $shape = ValueShape::list(ValueShape::variants(
        $mode === 'key' ? '' : 'type',
        ['known' => $type],
        mode: $mode === 'key' ? NestedDiscriminatorMode::Key : NestedDiscriminatorMode::Value,
    ));
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
        ->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()->shape($shape))->extras('extra'))
        ->withDto($type, DtoRules::create()->extras('extra'));
    $input = $mode === 'key' ? ['known' => ['id' => 1, 'future' => 2], 'meta' => 3] : ['type' => 'known', 'id' => 1, 'future' => 2];
    $dto = Hydrator::forRules($rules)->hydrate(['value' => [$input]], ValueDto::class);
    expect($dto->value[0]->id)->toBe(1)
        ->and($dto->value[0]->extra)->toBe($mode === 'value' ? ['type' => 'known', 'future' => 2] : ['future' => 2])
        ->and($dto->extra)->toBe($mode === 'key' ? ['value' => [['sourceKey' => 0, 'remainder' => ['meta' => 3]]]] : []);
})->with(['value', 'mapped-value', 'key']);

it('различает unknown политики и не подавляет ошибку известного варианта', function (NestedUnknownVariant $policy): void {
    $shape = ValueShape::list(ValueShape::variants('type', ['known' => OwnerDto::class], unknown: $policy), each: 'value');
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
        ->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()->shape($shape))->extras('extra'));
    $hydrator = Hydrator::forRules($rules);
    $raw = ['value' => ['type' => 'unknown', 'secret' => 'preserved'], 'meta' => false];
    if ($policy === NestedUnknownVariant::Error) {
        expect(HydrationRulesFixture::error(fn () => $hydrator->hydrate(['value' => [$raw]], ValueDto::class))->reason)
            ->toBe('unknown_nested_variant');
    } else {
        $dto = $hydrator->hydrate(['value' => [$raw]], ValueDto::class);
        expect($dto->value)->toBe($policy === NestedUnknownVariant::Skip ? [] : [$raw['value']])
            ->and($dto->extra)->toBe($policy === NestedUnknownVariant::Skip ? [] : ['value' => [['sourceKey' => 0, 'remainder' => ['meta' => false]]]]);
    }
    $error = HydrationRulesFixture::error(fn () => $hydrator->hydrate(['value' => [['value' => ['type' => 'known', 'id' => 'bad']]]], ValueDto::class));
    expect($error->reason)->toBe('invalid_field_type')->and($error->path)->toBe('value[0].id');
})->with(NestedUnknownVariant::cases());

it('передаёт receiver в DTO без конструктора и сохраняет Expected для обязательного поля', function (): void {
    $rules = HydrationRules::create()->withDto(NoConstructorDto::class, DtoRules::create()->extras('extra'));
    $hydrator = Hydrator::forRules($rules);
    $dto = $hydrator->hydrate(['id' => 1, 'future' => null], NoConstructorDto::class);
    expect($dto->id)->toBe(1)->and($dto->extra)->toBe(['future' => null]);
    $error = HydrationRulesFixture::error(fn () => $hydrator->hydrate([], NoConstructorDto::class));
    expect($error->path)->toBe('id')->and($error->sourcePath)->toBe('/id')
        ->and($error->sourcePathKind)->toBe(SourcePathKind::Expected);
});
