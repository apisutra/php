<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Enums\DataTransfer\ValueState;

final readonly class InvalidDefaultDto
{
    public function __construct(
        #[DefaultValue(value: 'conflict', provider: RejectingProvider::class, when: [ValueState::Null])]
        public ?string $value = null,
    ) {
    }
}
