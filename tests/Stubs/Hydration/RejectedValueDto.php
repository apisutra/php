<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Enums\DataTransfer\ValueState;

final readonly class RejectedValueDto
{
    public function __construct(
        #[DefaultValue(
            provider: RejectingProvider::class,
            when: [ValueState::Missing, ValueState::Null, ValueState::Present],
        )]
        public ?int $count = null,
    ) {
    }
}
