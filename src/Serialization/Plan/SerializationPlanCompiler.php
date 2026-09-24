<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Plan;

use ApiSutra\Localization\Message;
use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\DateTimeTo;
use ApiSutra\Attributes\DataTransfer\Map;
use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Metadata\MetadataCatalog;
use ApiSutra\Serialization\Concerns\ReflectionHelperTrait;

/** @internal Направленная компиляция в прежней точке материализации metadata. */
final readonly class SerializationPlanCompiler
{
    use ReflectionHelperTrait;

    private MetadataCatalog $catalog;

    public function __construct(private ?AttributeMetadataCache $cache, ?MetadataCatalog $catalog = null)
    {
        $this->catalog = $catalog ?? $cache?->catalog() ?? new MetadataCatalog();
    }

    /** @return list<SerializationFieldPlan> */
    public function bind(string $class): array
    {
        $key = $class . ':serialization-plan';
        $cached = $this->cache?->get($key);
        if ($cached !== null) {
            return $cached['plan']->bind();
        }

        $fields = [];
        $templates = [];
        $recipes = [];
        $cacheEnabled = $this->cache?->isEnabled() ?? false;
        foreach ($this->catalog->forClass($class)->properties as $property) {
            if ($property->isStatic()) {
                continue;
            }
            if (!$property->isPublic()) {
                throw new ConfigurationException(
                    new Message('serialization.public_properties_only', ['class' => $class, 'property' => $property->getName()]),
                );
            }

            $factories = [];
            $attributes = $this->getPropertyAttributes($property, [
                'to' => To::class,
                'map' => Map::class,
                'cast' => Cast::class,
                'dateTimeTo' => DateTimeTo::class,
            ], $factories);
            $field = new SerializationFieldPlan(
                $property->getName(),
                new SerializationValuePlan($property, $attributes['cast']),
                $attributes['to']->name ?? $attributes['map']->name ?? null,
                $attributes['dateTimeTo'],
            );
            $index = count($fields);
            $fields[] = $field;
            if ($cacheEnabled) {
                // Из текущих выходных атрибутов объектные аргументы принимает только Cast.
                $recipe = $factories['cast'] ?? null;
                $templates[] = $recipe === null ? $field : $field->withCast(null);
                if ($recipe !== null) {
                    $recipes[$index] = $recipe;
                }
            }
        }
        // Ошибка предыдущей декларации не публикует пригодную половину плана.
        if ($cacheEnabled) {
            $this->cache->set($key, ['plan' => new SerializationPlan($templates, $recipes)]);
        }
        return $fields;
    }
}
