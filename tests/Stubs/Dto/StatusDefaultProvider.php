<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use ApiSutra\Enums\DataTransfer\ValueState;

final class StatusDefaultProvider implements DefaultValueProviderInterface
{
    #[\Override]
    public function resolve(
        mixed $value,
        ValueState $state,
        array $source,
        HydrationContext $context,
    ): mixed {
        $marker = $source['marker'] ?? 'none';
        return 'provider:' . $state->value . ':' . $marker;
    }
}
