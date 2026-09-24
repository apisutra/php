<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\DataTransfer;

use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Serialization\Context\HydrationContext;

interface DefaultValueProviderInterface
{
    /**
     * Исходные данные DTO после unwrap/computed.
     *
     * @param array<string, mixed> $source
     */
    public function resolve(
        mixed $value,
        ValueState $state,
        array $source,
        HydrationContext $context,
    ): mixed;
}
