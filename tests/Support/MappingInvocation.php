<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Contracts\Interfaces\Casting\SerializationCastInterface;
use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;
use ApiSutra\Serialization\DtoSerializer;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\HydrationScope;

/** Стенд изолированных тестов встроенных cast; SDK вызывает обработчики через фасады. */
final class MappingInvocation
{
    public static function hydrate(HydrationCastInterface $cast, mixed $value): mixed
    {
        $scope = HydrationScope::bind(
            fn (array|object $data, string $class): object => Hydrator::default()->hydrate($data, $class),
            null,
            false,
        );
        return HydrationContext::invoke($scope, [], fn (HydrationContext $context): mixed => $cast->hydrate($value, $context));
    }

    public static function serialize(SerializationCastInterface $cast, mixed $value): mixed
    {
        return SerializationContext::invoke(
            fn (object $dto): array => DtoSerializer::default()->serialize($dto),
            [],
            fn (SerializationContext $context): mixed => $cast->serialize($value, $context),
        );
    }
}
