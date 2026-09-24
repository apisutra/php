<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Hydration;

use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\Map;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Metadata\MetadataCatalog;
use ApiSutra\Serialization\Concerns\ReflectionHelperTrait;
use ApiSutra\Serialization\Rules\CompiledDtoRules;
use WeakMap;

/** @internal План зависит от проверенной конфигурации; общий каталог остаётся нейтральным. */
final readonly class HydrationPlanCompiler
{
    use ReflectionHelperTrait;

    public function __construct(private ?AttributeMetadataCache $cache, private MetadataCatalog $catalog)
    {
    }

    public function bind(CompiledDtoRules $description): HydrationPlan
    {
        $cacheKey = $description->reflection->getName() . ':hydration-plan';
        $plans = $this->cache?->get($cacheKey)['plans'] ?? null;
        if (isset($plans[$description])) {
            return $plans[$description]->bind();
        }
        $metadata = $this->catalog->forClass($description->reflection->getName());
        $constructor = $metadata->constructorParameters();
        $parameters = [];
        foreach ($constructor ?? [] as $parameter) {
            $parameters[$parameter['name']] = $parameter['reflection'];
        }
        $fields = $templates = $recipes = $attributeTemplates = [];
        $enabled = $this->cache?->isEnabled() ?? false;
        foreach ($metadata->properties as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $factories = [];
            $attributes = $this->getPropertyAttributes($property, [
                'from' => From::class, 'map' => Map::class, 'nested' => Nested::class,
                'cast' => Cast::class, 'dateTimeFrom' => DateTimeFrom::class,
                'emptyStringAsNull' => EmptyStringAsNull::class, 'default' => DefaultValue::class,
            ], $factories);
            $name = $property->getName();
            $field = new HydrationFieldPlan(
                $property,
                $parameters[$name] ?? null,
                $description->enhanced ? $description->field($name) : null,
                $description->enhanced ? $description->policyFor($name) : null,
                $description->legacyProfile,
                $description->origins[$name] ?? 'legacy',
                $attributes,
            );
            $index = count($fields);
            $fields[] = $field;
            if ($enabled) {
                foreach ($factories as $key => $recipe) {
                    $attributes[$key] = null;
                }
                $templates[] = $factories === [] ? $field : $field->withAttributes($attributes);
                if ($factories !== []) {
                    $recipes[$index] = $factories;
                    $attributeTemplates[$index] = $attributes;
                }
            }
        }
        $receiver = $description->enhanced ? $description->declaration?->receiver : null;
        if ($enabled) {
            // Публикация после всех рецептов: ошибка не оставляет частичный план.
            // WeakMap не удерживает описание после полного сброса RuleSetCompiler.
            $plans = $this->cache->get($cacheKey)['plans'] ?? new WeakMap();
            $plans[$description] = new HydrationPlan(
                $metadata->reflection,
                $templates,
                $constructor,
                $receiver,
                $recipes,
                $attributeTemplates,
            );
            $this->cache->set($cacheKey, ['plans' => $plans]);
        }
        return new HydrationPlan($metadata->reflection, $fields, $constructor, $receiver);
    }
}
