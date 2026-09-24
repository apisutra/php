<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Contracts\Interfaces\Casting\SerializationCastInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

/** @internal Проверяет значения пользовательского профиля и сохраняет eager-создание его cast. */
final class ProfileCastResolver
{
    public static function resolve(mixed $cast, string $type, string $direction): HydrationCastInterface|SerializationCastInterface
    {
        if ($cast instanceof HydrationCastInterface || $cast instanceof SerializationCastInterface) {
            return $cast;
        }
        if (
            is_string($cast) && class_exists($cast)
            && (is_subclass_of($cast, HydrationCastInterface::class) || is_subclass_of($cast, SerializationCastInterface::class))
        ) {
            return new $cast();
        }
        throw new ConfigurationException(new Message('serialization.invalid_profile_cast', ['direction' => $direction, 'type' => $type]));
    }
}
