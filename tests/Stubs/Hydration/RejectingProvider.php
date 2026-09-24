<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Exceptions\Serialization\HydrationException;
use DomainException;

final readonly class RejectingProvider implements DefaultValueProviderInterface
{
    public function resolve(mixed $value, ValueState $state, array $source, HydrationContext $context): mixed
    {
        throw HydrationException::invalidValue(
            'provider_rejected_value',
            'accepted value',
            $state->value,
            ($source['detail'] ?? false) ? 'detail' : '',
            new DomainException('Причина отказа провайдера'),
        );
    }
}
