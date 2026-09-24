<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\RequiredInput;
use ApiSutra\Attributes\DataTransfer\ForbidExplicitNull;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Tests\Stubs\HydrationRules\FailingProvider;

final readonly class Presence
{
    public function __construct(
        #[RequiredInput]
        #[From('current', fallback: ['old'])]
        #[DefaultValue(provider: FailingProvider::class)]
        public int $id = 7,
        #[ForbidExplicitNull]
        #[DefaultValue(provider: FailingProvider::class, when: [ValueState::Null])]
        public ?int $stock = null,
    ) {
    }
}
