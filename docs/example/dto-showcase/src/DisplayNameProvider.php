<?php

declare(strict_types=1);

namespace Example\DtoShowcase;

use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Exceptions\Serialization\HydrationException;

final readonly class DisplayNameProvider implements DefaultValueProviderInterface
{
    public function resolve(mixed $value, ValueState $state, array $source, HydrationContext $context): mixed
    {
        $sku = $source['vendor_code'] ?? null;
        if (!is_string($sku)) {
            throw HydrationException::invalidValue('invalid_label_source', 'string vendor_code', get_debug_type($sku));
        }

        return 'Товар ' . $sku;
    }
}
