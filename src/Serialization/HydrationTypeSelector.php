<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

use ApiSutra\Contracts\Interfaces\DataTransfer\DtoInterface;
use ApiSutra\VO\Files\Base64File;
use DateTimeInterface;
use ReflectionProperty;
use UnitEnum;

final readonly class HydrationTypeSelector
{
    private PropertyTypeInspector $propertyTypeInspector;
    private SafeScalarHydrationCaster $safeScalarHydrationCaster;

    public function __construct(
        ?PropertyTypeInspector $propertyTypeInspector = null,
        ?SafeScalarHydrationCaster $safeScalarHydrationCaster = null,
    ) {
        $this->propertyTypeInspector = $propertyTypeInspector ?? new PropertyTypeInspector();
        $this->safeScalarHydrationCaster = $safeScalarHydrationCaster ?? new SafeScalarHydrationCaster();
    }

    public function resolveType(ReflectionProperty $property, mixed $value): ?string
    {
        $types = $this->propertyTypeInspector->getPropertyTypes($property);
        if ($types === []) {
            return null;
        }

        $resolved = array_find(
            $types,
            fn (string $type): bool => $this->propertyTypeInspector->matchesRuntimeType($value, $type),
        );
        if (is_string($resolved)) {
            return $resolved;
        }

        $resolved = array_find(
            $types,
            fn (string $type): bool => $this->canHydrateByType($type, $value),
        );

        return $resolved ?? $types[0];
    }

    private function canHydrateByType(string $type, mixed $value): bool
    {
        return match (true) {
            $this->safeScalarHydrationCaster->canHydrate($type, $value) => true,
            is_string($value) && is_subclass_of($type, DateTimeInterface::class) => true,
            is_scalar($value) && (enum_exists($type) || is_subclass_of($type, UnitEnum::class)) => true,
            (is_array($value) || is_object($value)) && is_subclass_of($type, DtoInterface::class) => true,
            is_string($value) && $type === Base64File::class => true,
            default => false,
        };
    }
}
