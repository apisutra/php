<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Hydration;

use ApiSutra\Localization\Message;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Enums\DataTransfer\DiscriminatorMode;
use ApiSutra\Serialization\Variants\VariantDefinition;
use ApiSutra\Serialization\Variants\VariantSelector;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Concerns\ReflectionHelperTrait;
use ApiSutra\Serialization\NestedObjectTypeResolver;
use ApiSutra\Serialization\Rules\HydrationScope;
use ApiSutra\Serialization\Rules\ContainerShape;
use ApiSutra\Serialization\Rules\NestedValueProcessor;
use ApiSutra\Serialization\Rules\RuleValueProcessor;
use ApiSutra\Support\ArrayPath;
use ApiSutra\VO\Files\Base64File;
use ReflectionProperty;

/** @internal Прежняя операция Nested без подмены её контракта строгой Shape. */
final readonly class LegacyNestedHydrator
{
    use ReflectionHelperTrait;

    public function __construct(
        private NestedObjectTypeResolver $nestedObjectTypeResolver = new NestedObjectTypeResolver(),
    ) {
    }

    public function hydrate(
        mixed $value,
        Nested $nested,
        ReflectionProperty $property,
        HydrationScope $scope,
    ): mixed {
        if ($value === null) {
            return null;
        }

        $objectType = $this->nestedObjectTypeResolver->resolve($nested, $property);
        if ($objectType !== null) {
            $kind = $scope->shape($value);
            if (
                (!is_array($value) && !is_object($value))
                || $kind === ContainerShape::List
                || ($kind === null && is_array($value) && $value !== [] && array_is_list($value))
            ) {
                throw HydrationException::invalidValue(
                    'unexpected_response_shape',
                    $objectType,
                    $kind->value ?? get_debug_type($value),
                );
            }

            return $scope->hydrateDto($value, $objectType);
        }

        $position = $property->getDeclaringClass()->getName() . '::$' . $property->getName();
        if (is_array($value) && $scope->shape($value) !== null) {
            $propertyType = $this->getPrimaryType($property);
            $target = $nested->type ?? $propertyType;
            $processed = (new NestedValueProcessor(new RuleValueProcessor()))->process($value, $nested, $target, $scope, $propertyType, $position);
            return $this->isDiscriminated($nested) || $target !== null && (class_exists($target) || interface_exists($target))
                ? HydrationCollections::wrap($processed->value, $propertyType) : $processed->value;
        }

        $propertyType = $this->getPrimaryType($property);
        $definition = null;
        if (is_array($value) && $this->isDiscriminated($nested)) {
            $definition = new VariantDefinition($nested->discriminator ?? '', $nested->map ?? [], $nested->discriminatorMode, $nested->unknownVariant);
            $itemClass = HydrationCollections::itemClass($propertyType);
            // Nested сохраняет KeepRaw: неизвестный сырой элемент проверяет сама коллекция.
            $definition->validate($position, allowSkip: true, allowRaw: true, target: $itemClass);
        }

        if ($nested->each !== null && is_array($value)) {
            $value = array_map(
                fn (mixed $item) => ArrayPath::getByPath($item, $nested->each),
                $value,
            );
        }

        if ($nested->itemCast !== null && is_array($value)) {
            $value = $this->applyNestedItemCast($value, $nested, $scope);
        }

        $targetType = $nested->type ?? $propertyType;

        if (is_array($value) && $definition !== null) {
            return $this->hydrateDiscriminatedNested(
                value: $value,
                definition: $definition,
                readyItems: $nested->itemCast !== null,
                propertyType: $propertyType,
                scope: $scope,
            );
        }

        if (is_array($value)) {
            if ($targetType !== null && (class_exists($targetType) || interface_exists($targetType))) {
                $items = [];
                foreach ($value as $item) {
                    if (is_object($item) && is_a($item, $targetType)) {
                        $items[] = $item;
                        continue;
                    }

                    if ($targetType === Base64File::class && is_string($item)) {
                        $items[] = new Base64File($item);
                        continue;
                    }

                    $items[] = HydrationCollections::item($item, $targetType, count($items), $scope);
                }

                return HydrationCollections::wrap($items, $propertyType);
            }

            return $value;
        }

        if ($targetType !== null && (class_exists($targetType) || interface_exists($targetType))) {
            if (!is_object($value)) {
                throw HydrationException::invalidValue(
                    'unexpected_response_shape',
                    $targetType,
                    get_debug_type($value),
                );
            }
            return $scope->hydrateDto($value, $targetType);
        }

        return $value;
    }

    /**
     * @param array<int|string, mixed> $items
     * @return array<int|string, mixed>
     */
    private function applyNestedItemCast(
        array $items,
        Nested $nested,
        HydrationScope $scope,
    ): array {
        $castClass = $nested->itemCast;
        if (!is_string($castClass) || trim($castClass) === '') {
            return $items;
        }

        if (!class_exists($castClass)) {
            throw new ConfigurationException(new Message('serialization.nested_itemcast_class_not_found', ['castClass' => $castClass]));
        }

        $cast = new $castClass();
        if (!$cast instanceof HydrationCastInterface) {
            throw new ConfigurationException(
                new Message('serialization.nested_itemcast_must_implement_hydrationcastinterface', ['castClass' => $castClass]),
            );
        }

        $index = 0;
        foreach ($items as $key => $item) {
            try {
                $items[$key] = $scope->cast($cast, $item);
            } catch (HydrationException $exception) {
                throw $exception->prependPath('[' . $index . ']');
            }
            $index++;
        }

        return $items;
    }

    public function isDiscriminated(Nested $nested): bool
    {
        if ($nested->map === null) {
            return false;
        }

        if ($nested->discriminatorMode === DiscriminatorMode::Key) {
            return true;
        }

        return $nested->discriminator !== null;
    }

    private function hydrateDiscriminatedNested(
        array $value,
        VariantDefinition $definition,
        bool $readyItems,
        ?string $propertyType,
        HydrationScope $scope,
    ): mixed {
        $items = [];
        $index = -1;
        foreach ($value as $item) {
            $index++;
            if ($readyItems && is_object($item) && $definition->accepts($item)) {
                $items[] = $item;
                continue;
            }
            try {
                $selection = VariantSelector::select($item, $definition);
            } catch (HydrationException $exception) {
                throw $exception->prependPath('[' . $index . ']');
            }
            if ($selection->skip) {
                continue;
            }
            $items[] = $selection->class === null ? $selection->payload
                : HydrationCollections::item($selection->payload, $selection->class, $index, $scope, selectVariants: false);
        }
        return HydrationCollections::wrap($items, $propertyType);
    }
}
