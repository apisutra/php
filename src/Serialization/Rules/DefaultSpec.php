<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

use ApiSutra\Enums\DataTransfer\ValueState;
use UnitEnum;

final readonly class DefaultSpec
{
    /** @param list<ValueState> $when */
    private function __construct(
        public mixed $value,
        public ?HandlerSpec $provider,
        public array $when,
    ) {
    }

    public static function value(int|float|string|bool|array|UnitEnum|null $value, ValueState ...$when): self
    {
        LiteralValues::assert($value);
        return new self($value, null, $when === [] ? [ValueState::Missing] : $when);
    }

    public static function provider(HandlerSpec $provider, ValueState ...$when): self
    {
        return new self(null, $provider, $when === [] ? [ValueState::Missing] : $when);
    }
}
