<?php

declare(strict_types=1);

use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Attributes\AttributeRegistry;
use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Pipeline\PipelineStage;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Metadata\MetadataCatalog;
use ApiSutra\Serialization\DtoSerializer;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\PropertyTypeInspector;
use ApiSutra\Serialization\Serializer;
use ApiSutra\Serialization\Rules\RuleSetCompiler;
use ApiSutra\Tests\Stubs\AttributeHydration\DiscoveryDto;
use ApiSutra\Tests\Stubs\MappingMetadata\LowercaseAttribute;
use ApiSutra\Tests\Stubs\MappingMetadata\SharedModel;
use ApiSutra\Tests\Stubs\MappingMetadata\StructuralChild;
use ApiSutra\Tests\Stubs\MappingMetadata\StructuralParent;
use ApiSutra\Tests\Stubs\MetadataIsolation\CreatedDefaultDto;
use ApiSutra\Tests\Stubs\MetadataIsolation\CreatedValue;
use ApiSutra\VO\Pipeline\PipelineContext;

beforeEach(function (): void {
    CreatedValue::$created = 0;
    CreatedValue::$fail = false;
});

it('прогревает структуру без вычисления args/defaults и без создания пользовательского профиля', function (bool $enabled): void {
    CreatedValue::$fail = true;
    $cache = new AttributeMetadataCache($enabled);
    $classes = [SharedModel::class, CreatedDefaultDto::class, DiscoveryDto::class];
    $cache->warmup($classes);
    $catalog = $cache?->catalog() ?? new MetadataCatalog();
    foreach ($classes as $class) {
        expect($catalog->forClass($class)->reflection->getName())->toBe($class);
    }
    $compiler = new RuleSetCompiler(metadata: $cache->catalog());
    expect($compiler->receiverFor(DiscoveryDto::class))->toBe('_extra')
        ->and(CreatedValue::$created)->toBe(0);
    $property = $catalog->forClass(SharedModel::class)->properties[0];
    expect($property->getAttributes(Cast::class))->toHaveCount(1);
    CreatedValue::$fail = false;
})->with([false, true]);

it('даёт трём потребителям и реестру одну структуру, изолируя материализованные объекты', function (bool $enabled): void {
    $cache = new AttributeMetadataCache($enabled);
    $cache->warmup([SharedModel::class]);
    $catalog = $cache?->catalog() ?? new MetadataCatalog();
    $before = $catalog->forClass(SharedModel::class);
    $hydrator = new Hydrator(new CastRegistry(), $cache);
    $serializer = new DtoSerializer(new CastRegistry(), $cache);
    $wire = new Serializer(new CastRegistry(), $cache);
    $registry = new AttributeRegistry(cache: $cache);
    for ($i = 0; $i < 2; $i++) {
        expect($hydrator->hydrate(['wire_number' => 0], SharedModel::class)->number)->toBe(1);
        $request = new SharedModel();
        expect($serializer->serialize($request))->toBe(['number' => 1]);
        $context = new PipelineContext($request, new ClientConfig(baseUrl: 'https://metadata.test'), 'metadata');
        expect($wire->serialize($request, $context)->url)->toBe('https://metadata.test/metadata?number=1');
        $registry->processStage($request, $context, PipelineStage::Started);
        expect(CreatedValue::$created)->toBe(($i + 1) * 3);
    }
    $after = $catalog->forClass(SharedModel::class);
    if ($enabled) {
        expect($after)->toBe($before);
        $property = $after->properties[0];
        expect($cache->get(SharedModel::class . ':hydration-plan')['plans'][$hydrator->descriptions()->forClass(SharedModel::class)]->fields[0]->property)->toBe($property)
            ->and($cache->get(SharedModel::class . ':serialization-plan')['plan']->bind()[0]->value->property)->toBe($property)
            ->and($cache->get(SharedModel::class . ':request-parts-plan')['plan']->fields[0]->value->property)->toBe($property)
            ->and($cache->get(SharedModel::class)['properties'][0]['property'])->toBe($property);
    } else {
        expect($after)->not->toBe($before);
    }
})->with([false, true]);

it('сохраняет declaring class, promotion и относительные типы без ранней направленной проверки', function (): void {
    $cache = new AttributeMetadataCache();
    $catalog = $cache?->catalog() ?? new MetadataCatalog();
    $description = $catalog->forClass(StructuralChild::class);
    $properties = [];
    foreach ($description->properties as $property) {
        $properties[$property->getName()] = $property;
    }
    // PHP 8.5 возвращает уже разрешённые self/parent; проверяем область типа на обеих версиях.
    $types = new PropertyTypeInspector();
    expect($description->constructorParameters())->toHaveCount(1)
        ->and($properties['id']->isPromoted())->toBeTrue()
        ->and($properties['id']->getDeclaringClass()->getName())->toBe(StructuralParent::class)
        ->and($types->getPropertyTypes($properties['ancestor']))->toBe([StructuralParent::class])
        ->and($types->getPropertyTypes($properties['hidden']))->toBe([StructuralParent::class])
        ->and($properties['counter']->isStatic())->toBeTrue();
    $dto = new StructuralChild();
    expect($properties['uninitialized']->isInitialized($dto))->toBeFalse();
    $dto->uninitialized = 'set';
    expect($catalog->forClass(StructuralChild::class))->toBe($description)
        ->and($properties['uninitialized']->isInitialized($dto))->toBeTrue();
    $serializer = new DtoSerializer(new CastRegistry(), $cache);
    expect(fn () => $serializer->serialize($dto))->toThrow(ConfigurationException::class, 'only public');
});

it('сохраняет нечувствительное к регистру сопоставление ReflectionAttribute', function (): void {
    // Регистр пути PSR-4 не относится к сопоставлению уже загруженной декларации PHP.
    class_exists(From::class);
    expect(Hydrator::default()->hydrate(['wire_id' => 7], LowercaseAttribute::class)->id)->toBe(7);
});
