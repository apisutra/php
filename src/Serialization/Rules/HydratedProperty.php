<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

use ApiSutra\Enums\DataTransfer\ValueState;

/** @internal Значение свойства и след его чтения в рамках одного DTO. */
final readonly class HydratedProperty
{
    /** @param list<int|string> $segments */
    public function __construct(
        public mixed $value,
        public ValueState $state,
        public array $segments,
        public SourceLocation $location,
        public SourceConsumption $consumed,
    ) {
    }
}
