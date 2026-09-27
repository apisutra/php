<?php

declare(strict_types=1);

use ApiSutra\Enums\DataTransfer\DiscriminatorMode;
use ApiSutra\Enums\DataTransfer\UnknownVariant;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HandlerSpec;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\ValueShape;
use ApiSutra\Tests\Stubs\HydrationRules\ValueDto;
use ApiSutra\Tests\Stubs\JsonContainerShapes\Node;
use ApiSutra\Tests\Stubs\PolymorphicInput\CastFallbackDto;
use ApiSutra\Tests\Stubs\PolymorphicInput\LegacyFallbackDto;
use ApiSutra\Tests\Stubs\PolymorphicInput\RawDto;
use ApiSutra\Tests\Stubs\PolymorphicInput\RawDtoCast;
use ApiSutra\Tests\Stubs\PolymorphicInput\TypedValueDto;
use ApiSutra\Tests\Support\HydrationRulesFixture as Fixture;

function variantFallbackHydrator(ValueShape $shape, string $class = ValueDto::class): Hydrator
{
    return Hydrator::forRules(HydrationRules::create()->withDto($class, DtoRules::create()->field('value', FieldRule::create()->shape($shape))));
}

it('создаёт fallback на одиночном поле и в списке без потери исходного unknown узла', function (bool $list): void {
    $variant = ValueShape::variants('event.type', ['node' => Node::class], unknown: RawDto::class);
    $hydrator = variantFallbackHydrator($list ? ValueShape::list($variant) : $variant);
    $unknown = ['event' => ['type' => 'future'], 'payload' => ['a' => 1]];
    $json = json_encode(['value' => $list ? [$unknown] : $unknown], JSON_THROW_ON_ERROR);
    $value = $hydrator->hydrateJson($json, ValueDto::class)->value;
    expect(($list ? $value[0] : $value)->raw)->toBe($unknown);
    $known = ['event' => ['type' => 'node'], 'name' => 'known', 'child' => []];
    $json = json_encode(['value' => $list ? [$known] : $known], JSON_THROW_ON_ERROR);
    $error = Fixture::error(fn () => $hydrator->hydrateJson($json, ValueDto::class));
    expect($error->reason)->toBe('invalid_object_shape')->and($error->sourcePath)->toBe($list ? '/value/0/child' : '/value/child');
})->with([false, true]);

it('Nested принимает готовый fallback от itemCast без повторной гидратации', function (bool $json): void {
    $hydrator = Hydrator::default();
    $source = ['value' => [['type' => 'future', 'payload' => ['a' => 1]]]];
    $dto = $json ? $hydrator->hydrateJson(json_encode($source, JSON_THROW_ON_ERROR), CastFallbackDto::class) : $hydrator->hydrate($source, CastFallbackDto::class);
    expect($dto->value[0])->toBeInstanceOf(RawDto::class)->and($dto->value[0]->raw)->toBe($source['value'][0]);
})->with([false, true]);

it('сохраняет Key wrapper неизвестного и payload известного после each', function (): void {
    $shape = ValueShape::list(ValueShape::variants('wrapped', ['node' => Node::class], DiscriminatorMode::Key, RawDto::class), each: 'entry');
    $hydrator = variantFallbackHydrator($shape);
    $dto = $hydrator->hydrateJson('{"value":[{"entry":{"wrapped":{"node":{"name":"yes"}}}},{"entry":{"wrapped":{"future":{"name":"raw"}}}}]}', ValueDto::class);
    expect($dto->value[0])->toBeInstanceOf(Node::class)->and($dto->value[0]->name)->toBe('yes')
        ->and($dto->value[1]->raw)->toBe(['wrapped' => ['future' => ['name' => 'raw']]]);
});

it('проверяет тип тега независимо от unknown политики без неявного scalar приведения', function (): void {
    $hydrator = variantFallbackHydrator(ValueShape::variants('event.type', [1 => Node::class, '01' => RawDto::class, '' => RawDto::class], unknown: RawDto::class));
    foreach ([1, '1'] as $tag) {
        expect($hydrator->hydrate(['value' => ['event' => ['type' => $tag]]], ValueDto::class)->value)->toBeInstanceOf(Node::class);
    }
    foreach (['01', '', null, 'future'] as $tag) {
        expect($hydrator->hydrate(['value' => ['event' => ['type' => $tag]]], ValueDto::class)->value)->toBeInstanceOf(RawDto::class);
    }
    foreach ([true, false, 1.0, [], new stdClass(), new class implements Stringable {
        public function __toString(): string { return '1'; }
    }] as $tag) {
        $error = Fixture::error(fn () => $hydrator->hydrate(['value' => ['event' => ['type' => $tag]]], ValueDto::class));
        expect($error->reason)->toBe('invalid_discriminator_type')->and($error->path)->toBe('value');
    }
});

it('поясняет позицию и явный выбор Error вместо несовместимого default KeepRaw', function (): void {
    $shape = ValueShape::variants('type', ['node' => Node::class]);
    expect(fn () => variantFallbackHydrator($shape, TypedValueDto::class))->toThrow(ConfigurationException::class, 'single field');
    $hydrator = variantFallbackHydrator(ValueShape::variants('type', ['node' => Node::class], unknown: UnknownVariant::Error), TypedValueDto::class);
    expect($hydrator->hydrateJson('{"value":{"type":"node"}}', TypedValueDto::class)->value)->toBeInstanceOf(Node::class);
    expect(fn () => variantFallbackHydrator(ValueShape::variants('type', ['node' => Node::class], unknown: UnknownVariant::Skip)))
        ->toThrow(ConfigurationException::class, 'single field');
});

it('учитывает fallback в проверке готового результата cast', function (): void {
    $shape = ValueShape::variants('type', ['node' => Node::class], unknown: RawDto::class);
    $rules = HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()->cast(new HandlerSpec(RawDtoCast::class), $shape)));
    expect(Hydrator::forRules($rules)->hydrate(['value' => ['type' => 'future']], ValueDto::class)->value->raw)->toBe(['type' => 'future']);
});

it('Nested использует те же fallback и проверку типа с PHP и JSON входом', function (bool $json): void {
    $hydrator = Hydrator::default();
    $data = ['value' => [['type' => 'node', 'name' => 'known'], ['type' => 'future', 'payload' => ['a' => 1]]]];
    $dto = $json ? $hydrator->hydrateJson(json_encode($data, JSON_THROW_ON_ERROR), LegacyFallbackDto::class) : $hydrator->hydrate($data, LegacyFallbackDto::class);
    expect($dto->value[0])->toBeInstanceOf(Node::class)->and($dto->value[1]->raw)->toBe($data['value'][1]);
    $data['value'][0]['type'] = true;
    expect(Fixture::error(fn () => $json ? $hydrator->hydrateJson(json_encode($data, JSON_THROW_ON_ERROR), LegacyFallbackDto::class) : $hydrator->hydrate($data, LegacyFallbackDto::class))->reason)->toBe('invalid_discriminator_type');
})->with([false, true]);
