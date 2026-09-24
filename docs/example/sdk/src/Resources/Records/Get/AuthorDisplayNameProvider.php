<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records\Get;

use ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Context\HydrationContext;
use Override;

final readonly class AuthorDisplayNameProvider implements DefaultValueProviderInterface
{
    #[Override]
    public function resolve(mixed $value, ValueState $state, array $source, HydrationContext $context): mixed
    {
        $firstName = $source['first_name'] ?? null;
        $lastName = $source['last_name'] ?? null;
        if (!is_string($firstName) || !is_string($lastName)) {
            throw HydrationException::invalidValue(
                'invalid_author_name',
                'string first_name and last_name',
                'invalid fields',
            );
        }

        // Явное display_name из API имеет приоритет над вычислением по умолчанию.
        return $firstName . ' ' . $lastName;
    }
}
