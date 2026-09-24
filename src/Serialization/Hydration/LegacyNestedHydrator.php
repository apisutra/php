<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Hydration;

use ApiSutra\Localization\Message;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Enums\DataTransfer\NestedDiscriminatorMode;
use ApiSutra\Enums\DataTransfer\NestedUnknownVariant;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Concerns\ReflectionHelperTrait;
use ApiSutra\Serialization\NestedObjectTypeResolver;
use ApiSutra\Serialization\Rules\HydrationScope;
use ApiSutra\Support\ArrayPath;
use ApiSutra\VO\Files\Base64File;
use ReflectionClass;
use ReflectionProperty;
use Stringable;

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
            if (
                (!is_array($value) && !is_object($value))
                || (is_array($value) && $value !== [] && array_is_list($value))
            ) {
                throw HydrationException::invalidValue(
                    'unexpected_response_shape',
                    $objectType,
                    get_debug_type($value),
                );
            }

            return $scope->hydrateDto($value, $objectType);
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

        $propertyType = $this->getPrimaryType($property);
        $targetType = $nested->type ?? $propertyType;

        if (is_array($value) && $this->isDiscriminated($nested)) {
            return $this->hydrateDiscriminatedNested(
                value: $value,
                nested: $nested,
                propertyType: $propertyType,
                scope: $scope,
            );
        }

        if (is_array($value)) {
            if ($targetType !== null && class_exists($targetType)) {
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

        if ($targetType !== null && class_exists($targetType)) {
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

        if ($nested->discriminatorMode === NestedDiscriminatorMode::Key) {
            return true;
        }

        return $nested->discriminator !== null;
    }

    private function hydrateDiscriminatedNested(
        array $value,
        Nested $nested,
        ?string $propertyType,
        HydrationScope $scope,
    ): mixed {
        $items = [];
        $index = -1;

        foreach ($nested->map ?? [] as $class) {
            if (!is_string($class) || !class_exists($class) || (new ReflectionClass($class))->isAbstract() || enum_exists($class)) {
                throw new ConfigurationException(new Message('serialization.nested_map_must_contain_available_dto_classes'));
            }
        }

        foreach ($value as $item) {
            $index++;
            [$discriminator, $payload, $rawItem] = $this->resolveDiscriminatorPayload($item, $nested);
            $class = $this->resolveDiscriminatorClass($nested, $discriminator);

            if ($class === null) {
                if ($nested->unknownVariant === NestedUnknownVariant::Skip) {
                    continue;
                }

                if ($nested->unknownVariant === NestedUnknownVariant::Error) {
                    throw HydrationException::invalidValue(
                        'unknown_nested_variant',
                        'variant: ' . implode('|', array_keys($nested->map ?? [])),
                        $discriminator === null ? 'missing' : 'string',
                        '[' . $index . ']',
                    );
                }

                $items[] = $rawItem;
                continue;
            }

            $items[] = HydrationCollections::item($payload, $class, $index, $scope);
        }

        return HydrationCollections::wrap($items, $propertyType);
    }

    /** @return array{?string, mixed, mixed} */
    private function resolveDiscriminatorPayload(mixed $item, Nested $nested): array
    {
        return $nested->discriminatorMode === NestedDiscriminatorMode::Key
            ? $this->resolveKeyDiscriminatorPayload($item, $nested)
            : $this->resolveValueDiscriminatorPayload($item, $nested);
    }

    /** @return array{?string, mixed, mixed} */
    private function resolveValueDiscriminatorPayload(mixed $item, Nested $nested): array
    {
        $discriminator = null;
        if ($nested->discriminator !== null) {
            $resolved = ArrayPath::getByPath($item, $nested->discriminator);
            if (is_scalar($resolved) || $resolved instanceof Stringable) {
                $discriminator = (string) $resolved;
            }
        }

        return [$discriminator, $item, $item];
    }

    /** @return array{?string, mixed, mixed} */
    private function resolveKeyDiscriminatorPayload(mixed $item, Nested $nested): array
    {
        $rawItem = $item;
        $source = $item;

        if ($nested->discriminator !== null && $nested->discriminator !== '') {
            $source = ArrayPath::getByPath($item, $nested->discriminator);
        }

        if (!is_array($source) || $source === []) {
            return [null, $source, $rawItem];
        }

        $key = array_key_first($source);
        return [(string) $key, $source[$key], $rawItem];
    }

    private function resolveDiscriminatorClass(Nested $nested, ?string $discriminator): ?string
    {
        if ($discriminator === null || !is_array($nested->map)) {
            return null;
        }

        $class = $nested->map[$discriminator] ?? null;

        return is_string($class) ? $class : null;
    }
}
