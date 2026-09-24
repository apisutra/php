<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Attributes\DataTransfer\DtoHydrate;
use ApiSutra\Enums\DataTransfer\EmptyStringBehavior;
use ApiSutra\Enums\DataTransfer\ValueState;

#[DtoHydrate(emptyStringBehavior: EmptyStringBehavior::NullIfBlank)]
final readonly class ProviderPolicyDto
{
    public function __construct(
        #[DefaultValue(provider: StateProvider::class, when: [ValueState::Null, ValueState::Present])]
        public ?string $value = null,
    ) {
    }
}
