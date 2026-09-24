<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Exceptions\Serialization\HydrationException;

final readonly class ListProvider implements DefaultValueProviderInterface
{
    public function resolve(mixed $value, ValueState $state, array $source, HydrationContext $context): mixed
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw HydrationException::invalidValue('invalid_list_shape', 'list', get_debug_type($value));
        }
        return $value;
    }
}
