<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

use ApiSutra\Localization\Message;
use ApiSutra\Attributes\DataTransfer\DtoHydrate;
use ApiSutra\Attributes\DataTransfer\DtoHydrationProfile as DtoHydrationProfileAttribute;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\DtoHydrationPolicy;
use ApiSutra\Contracts\Interfaces\Serialization\DtoHydrationProfileInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Serialization\VO\ResolvedDtoHydration;
use ReflectionClass;

final readonly class DtoHydrationProfileResolver
{
    public function resolveForDto(string $dtoClass, ?DtoHydrationPolicy $base = null): ResolvedDtoHydration
    {
        $profile = $this->resolveBoundProfile($dtoClass);
        $policy = $profile?->policy() ?? $base ?? new DtoHydrationPolicy();
        $override = $this->resolveClassOverride($dtoClass);

        if ($override !== null) {
            $policy = $override->toPolicy($policy);
        }

        return new ResolvedDtoHydration(
            policy: $policy,
            casts: $this->buildCastRegistry($profile),
        );
    }

    private function resolveBoundProfile(string $dtoClass): ?DtoHydrationProfileInterface
    {
        foreach ($this->classHierarchy($dtoClass) as $class) {
            $attribute = ($class->getAttributes(DtoHydrationProfileAttribute::class)[0] ?? null)?->newInstance();

            if (!$attribute instanceof DtoHydrationProfileAttribute) {
                continue;
            }

            if (!class_exists($attribute->class)) {
                throw new ConfigurationException(new Message('serialization.hydration_profile_class_not_found', ['class' => $attribute->class]));
            }

            $profile = new $attribute->class();

            if (!$profile instanceof DtoHydrationProfileInterface) {
                throw new ConfigurationException(
                    new Message('serialization.hydration_profile_invalid', ['class' => $attribute->class]),
                );
            }

            return $profile;
        }

        return null;
    }

    private function resolveClassOverride(string $dtoClass): ?DtoHydrate
    {
        foreach ($this->classHierarchy($dtoClass) as $class) {
            $attribute = ($class->getAttributes(DtoHydrate::class)[0] ?? null)?->newInstance();

            if ($attribute instanceof DtoHydrate) {
                return $attribute;
            }
        }

        return null;
    }

    /**
     * @return array<int, ReflectionClass<object>>
     */
    private function classHierarchy(string $class): array
    {
        $hierarchy = [];
        $current = new ReflectionClass($class);

        do {
            $hierarchy[] = $current;
            $current = $current->getParentClass();
        } while ($current !== false);

        return $hierarchy;
    }

    private function buildCastRegistry(?DtoHydrationProfileInterface $profile): CastRegistry
    {
        $registry = new CastRegistry();

        if ($profile === null) {
            return $registry;
        }

        foreach ($profile->casts() as $type => $cast) {
            $registry->register($type, ProfileCastResolver::resolve($cast, $type, 'hydration'));
        }

        return $registry;
    }
}
