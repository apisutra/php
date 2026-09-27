<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records\Get;

use ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;
use Override;

final readonly class LocalizedTitlesCast implements CastInterface
{
    #[Override]
    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        // InputShape проверяет JSON object до cast; здесь — языковые коды и строки названий.
        if (!is_array($value)) {
            throw HydrationException::invalidValue(
                'invalid_localized_titles',
                'language-to-title map',
                get_debug_type($value),
            );
        }
        foreach ($value as $language => $title) {
            if (
                !is_string($language)
                || preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/D', $language) !== 1
                || !is_string($title)
            ) {
                throw HydrationException::invalidValue(
                    'invalid_localized_titles',
                    'language code and string title',
                    get_debug_type($title),
                );
            }
        }
        return $value;
    }

    #[Override]
    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        // Проверка не меняет словарь; на выходе сохраняем его ключи и значения.
        return $value;
    }
}
