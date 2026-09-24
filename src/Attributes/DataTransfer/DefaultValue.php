<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\DataTransfer;

use Attribute;
use ApiSutra\Enums\DataTransfer\ValueState;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class DefaultValue
{
    public array $when;

    public function __construct(
        public int|float|string|bool|array|null $value = null,
        public ?string $provider = null,
        array $when = [ValueState::Missing],
    ) {
        $this->when = $when === [] ? [ValueState::Missing] : $when;
    }
}
