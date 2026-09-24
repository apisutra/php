<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

use ApiSutra\Localization\Message;
use ApiSutra\Attributes\DataTransfer\DtoSerializationProfile as DtoSerializationProfileAttribute;
use ApiSutra\Attributes\DataTransfer\DtoSerialize;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Contracts\Interfaces\Serialization\DtoSerializationProfileInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Serialization\VO\ResolvedDtoSerialization;
use ReflectionClass;

final readonly class DtoSerializationProfileResolver
{
    public function resolveForDto(string $dtoClass, ?ClientConfig $config = null): ResolvedDtoSerialization
    {
        $boundProfile = $this->resolveBoundProfile($dtoClass);
        $configProfile = $config?->dtoSerializationProfile;

        $profile = $this->resolveProfile($boundProfile, $configProfile, $dtoClass);
        $policy = $this->resolveBasePolicy($profile, $config);
        $override = $this->resolveClassOverride($dtoClass);
        if ($override !== null) {
            $policy = $override->toPolicy($policy);
        }

        return new ResolvedDtoSerialization(
            policy: $policy,
            casts: $this->buildCastRegistry($profile),
        );
    }

    public function resolveForBody(?ClientConfig $config = null): ResolvedDtoSerialization
    {
        $profile = $config?->dtoSerializationProfile;

        return new ResolvedDtoSerialization(
            policy: $this->resolveBasePolicy($profile, $config),
            casts: $this->buildCastRegistry($profile),
        );
    }

    public function resolveForWireDto(string $dtoClass, ?ClientConfig $config = null): ResolvedDtoSerialization
    {
        $boundProfile = $this->resolveBoundProfile($dtoClass);
        $configProfile = $config?->dtoSerializationProfile;
        $profile = $boundProfile ?? $configProfile;
        $dxPolicy = $this->resolveBasePolicy($profile, $config);

        return new ResolvedDtoSerialization(
            policy: $config->wireBodySerializationPolicy ?? $this->deriveWirePolicy($dxPolicy, $config),
            casts: $this->buildCastRegistry($profile),
        );
    }

    private function resolveProfile(
        ?DtoSerializationProfileInterface $boundProfile,
        ?DtoSerializationProfileInterface $configProfile,
        string $dtoClass,
    ): ?DtoSerializationProfileInterface {
        if ($boundProfile !== null && $configProfile !== null && $boundProfile::class !== $configProfile::class) {
            throw new ConfigurationException(
                new Message('serialization.profile_conflict', ['class' => $dtoClass, 'bound' => $boundProfile::class, 'configured' => $configProfile::class]),
            );
        }

        return $boundProfile ?? $configProfile;
    }

    private function resolveBasePolicy(
        ?DtoSerializationProfileInterface $profile,
        ?ClientConfig $config,
    ): DtoSerializationPolicy {
        if ($profile !== null) {
            return $profile->policy();
        }

        return new DtoSerializationPolicy();
    }

    private function deriveWirePolicy(DtoSerializationPolicy $dxPolicy, ?ClientConfig $config): DtoSerializationPolicy
    {
        return new DtoSerializationPolicy(
            enumOutput: \ApiSutra\Enums\Serialization\EnumOutput::Value,
            strictEnums: false,
            namingStrategy: $dxPolicy->namingStrategy,
            serializeNulls: $dxPolicy->serializeNulls,
            dateTime: $config->requestDateTime ?? $dxPolicy->dateTime,
        );
    }

    private function resolveBoundProfile(string $dtoClass): ?DtoSerializationProfileInterface
    {
        foreach ($this->classHierarchy($dtoClass) as $class) {
            $attribute = ($class->getAttributes(DtoSerializationProfileAttribute::class)[0] ?? null)?->newInstance();
            if (!$attribute instanceof DtoSerializationProfileAttribute) {
                continue;
            }

            if (!class_exists($attribute->class)) {
                throw new ConfigurationException(new Message('serialization.serialization_profile_class_not_found', ['class' => $attribute->class]));
            }

            $profile = new $attribute->class();
            if (!$profile instanceof DtoSerializationProfileInterface) {
                throw new ConfigurationException(
                    new Message('serialization.serialization_profile_invalid', ['class' => $attribute->class]),
                );
            }

            return $profile;
        }

        return null;
    }

    private function resolveClassOverride(string $dtoClass): ?DtoSerialize
    {
        foreach ($this->classHierarchy($dtoClass) as $class) {
            $attribute = ($class->getAttributes(DtoSerialize::class)[0] ?? null)?->newInstance();
            if ($attribute instanceof DtoSerialize) {
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

    private function buildCastRegistry(?DtoSerializationProfileInterface $profile): CastRegistry
    {
        $registry = new CastRegistry();
        if ($profile === null) {
            return $registry;
        }

        foreach ($profile->casts() as $type => $cast) {
            $registry->register($type, ProfileCastResolver::resolve($cast, $type, 'serialization'));
        }

        return $registry;
    }
}
