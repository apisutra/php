<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Exceptions\Serialization\HydrationException;

final readonly class FailingProvider implements DefaultValueProviderInterface
{
    public function resolve(mixed $value, ValueState $state, array $source, HydrationContext $context): mixed
    {
        throw HydrationException::invalidValue('invalid_field_type', 'valid data', 'invalid');
    }
}
