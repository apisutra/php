<?php

declare(strict_types=1);

use ApiSutra\Config\HydrationConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Tests\Support\HydrationRulesFixture;
use ApiSutra\Enums\DataTransfer\EmptyStringBehavior;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DefaultSpec;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Serialization\Rules\ValueShape;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\HydrationRules\BackedState;
use ApiSutra\Tests\Stubs\HydrationRules\OptionalDto;
use ApiSutra\Tests\Stubs\HydrationRules\ScalarDto;
use ApiSutra\Tests\Stubs\HydrationRules\UnitState;
use ApiSutra\Tests\Stubs\HydrationRules\ValueDto;
use ApiSutra\Tests\Stubs\Requests\HydrationProbeRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

it('строго проверяет scalar поля до reflection через standalone и HTTP', function (string $field, mixed $value, bool $valid, string $reason, bool $http, bool $common): void {
    $policy = new RulePolicy(scalars: ScalarPolicy::Strict);
    $config = $common ? new HydrationConfig(policy: $policy) : new HydrationConfig(rules: HydrationRules::create($policy));
    $operation = function () use ($config, $field, $value, $http): object {
        if (!$http) {
            return Hydrator::forConfig($config)->hydrate([$field => $value], ScalarDto::class);
        }
        $transport = new MockTransport();
        $transport->fake([HydrationProbeRequest::class => MockResponse::success([$field => $value])]);
        $client = new TestClient(new ClientConfig(baseUrl: 'https://rules.test', hydration: $config), $transport);
        return $client->send(new HydrationProbeRequest(ScalarDto::class))->dataOrFail();
    };
    if ($valid) {
        $expected = $field === 'float' && is_int($value) ? (float) $value : $value;
        expect($operation()->{$field})->toBe($expected);
    } else {
        $error = HydrationRulesFixture::error($operation);
        expect($error->reason)->toBe($reason)->and($error->path)->toBe($field)
            ->and($error->sourcePath)->toBe('/' . $field);
    }
})->with([
    ['int', 7, true, ''], ['int', '7', false, 'invalid_field_type'],
    ['int', 7.5, false, 'invalid_field_type'], ['int', false, false, 'invalid_field_type'],
    ['int', '9223372036854775808', false, 'integer_out_of_range'],
    ['float', 1, true, ''], ['float', 1.5, true, ''], ['float', '1', false, 'invalid_field_type'],
    ['float', false, false, 'invalid_field_type'],
    ['float', 9007199254740991, true, ''], ['float', 9007199254740992, true, ''],
    ['float', -9007199254740991, true, ''], ['float', -9007199254740992, true, ''],
    ['float', 9007199254740993, false, 'invalid_field_type'], ['float', 9007199254740994, false, 'invalid_field_type'],
    ['float', -9007199254740993, false, 'invalid_field_type'], ['float', -9007199254740994, false, 'invalid_field_type'],
    ['float', PHP_INT_MIN, false, 'invalid_field_type'], ['float', PHP_INT_MAX, false, 'invalid_field_type'],
    ['int', PHP_INT_MIN, true, ''], ['int', PHP_INT_MAX, true, ''],
    ['bool', false, true, ''], ['bool', 0, false, 'invalid_field_type'], ['bool', 'true', false, 'invalid_field_type'],
    ['string', '', true, ''], ['string', 7, false, 'invalid_field_type'], ['string', true, false, 'invalid_field_type'],
    ['yes', true, true, ''], ['yes', false, false, 'invalid_field_type'],
    ['no', false, true, ''], ['no', true, false, 'invalid_field_type'],
    ['union', '7', true, ''], ['union', 7, true, ''], ['union', 7.5, false, 'invalid_field_type'],
    ['wide', 7, true, ''], ['wide', '7', true, ''], ['wide', 7.5, true, ''],
    ['any', null, true, ''], ['any', [1, 'x', null], true, ''],
])->with([false, true])->with([false, true]);

it('различает optional missing, explicit null, нормализованную строку и default null', function (): void {
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict, emptyString: EmptyStringBehavior::NullIfEmpty))
        ->withDto(OptionalDto::class, DtoRules::create()->field('count', FieldRule::create()->forbidExplicitNull()));
    $hydrator = Hydrator::forRules($rules);
    expect($hydrator->hydrate([], OptionalDto::class)->count)->toBeNull()
        ->and($hydrator->hydrate(['count' => ''], OptionalDto::class)->count)->toBeNull()
        ->and($hydrator->hydrate(['count' => 7], OptionalDto::class)->count)->toBe(7);
    expect(HydrationRulesFixture::error(fn () => $hydrator->hydrate(['count' => null], OptionalDto::class))->reason)
        ->toBe('explicit_null_not_allowed');
    expect(HydrationRulesFixture::error(fn () => $hydrator->hydrate(['count' => '7'], OptionalDto::class))->reason)
        ->toBe('invalid_field_type');
    $defaults = HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()->default(DefaultSpec::value(null))));
    expect(Hydrator::forRules($defaults)->hydrate([], ValueDto::class)->value)->toBeNull();
});

it('проверяет объявленные scalar списки и рекурсивные списки', function (array $input, ValueShape $shape, ?string $errorPath): void {
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
        ->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()->shape($shape)));
    $hydrate = fn (): object => Hydrator::forRules($rules)->hydrate(['value' => $input], ValueDto::class);
    if ($errorPath === null) {
        expect($hydrate()->value)->toBe($input);
    } else {
        expect(HydrationRulesFixture::error($hydrate)->path)->toBe($errorPath);
    }
})->with([
    [[1, 2], ValueShape::list(ValueShape::int()), null],
    [[1, '2'], ValueShape::list(ValueShape::int()), 'value[1]'],
    [[1, null], ValueShape::list(ValueShape::int()), 'value[1]'],
    [[], ValueShape::list(ValueShape::int()), null],
    [[1, null], ValueShape::list(ValueShape::nullable(ValueShape::int())), null],
    [[['a'], ['b', 3]], ValueShape::list(ValueShape::list(ValueShape::string())), 'value[1][1]'],
    [[0 => 1, 2 => 2], ValueShape::list(ValueShape::int()), 'value'],
    [['key' => 1], ValueShape::list(ValueShape::int()), 'value'],
]);

it('сохраняет enum cases в literal default отдельно и внутри массивов', function (UnitEnum $case): void {
    foreach ([$case, ['nested' => [$case]]] as $value) {
        $rules = HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()->default(DefaultSpec::value($value))));
        $hydrator = Hydrator::forRules($rules);
        expect($hydrator->hydrate([], ValueDto::class)->value)->toBe($value)
            ->and($hydrator->hydrate([], ValueDto::class)->value)->toBe($value);
    }
})->with([UnitState::Ready, BackedState::Ready]);
