<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingHydration;

use ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Context\HydrationContext;

final readonly class Provider implements DefaultValueProviderInterface
{
    public function resolve(mixed $value, ValueState $state, array $source, HydrationContext $context): mixed
    {
        Trace::$events[] = 'provider:' . $state->name;
        if ($value === 'bad') {
            throw HydrationException::invalidValue('provider_failed', 'int', 'string');
        }
        return $value === null ? 10 : $value + 1;
    }
}
