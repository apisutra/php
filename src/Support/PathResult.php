<?php

declare(strict_types=1);

namespace ApiSutra\Support;

use ApiSutra\Enums\DataTransfer\ValueState;

/**
 * Результат извлечения значения по пути.
 */
readonly class PathResult
{
    public function __construct(
        public ValueState $state,
        public mixed $value,
    ) {
    }

    public function isPresent(): bool
    {
        return $this->state === ValueState::Present;
    }

    public function isNull(): bool
    {
        return $this->state === ValueState::Null;
    }

    public function isMissing(): bool
    {
        return $this->state === ValueState::Missing;
    }
}
