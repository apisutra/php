<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Tests\Stubs\Casts\UppercaseCast;

final readonly class ProviderStatesDto
{
    public function __construct(
        #[DefaultValue(provider: StateProvider::class, when: [ValueState::Null, ValueState::Present])]
        public ?string $keep = null,
        #[EmptyStringAsNull]
        #[DefaultValue(provider: StateProvider::class, when: [ValueState::Null, ValueState::Present])]
        public ?string $normalized = null,
        #[Cast(UppercaseCast::class)]
        #[EmptyStringAsNull]
        #[DefaultValue(provider: StateProvider::class, when: [ValueState::Null, ValueState::Present])]
        public ?string $cast = null,
    ) {
    }
}
