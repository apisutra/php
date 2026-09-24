<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Enums\DataTransfer\ValueState;

final readonly class CheckedListDto
{
    /** @param list<AddressDto|array<string, mixed>>|null $items */
    public function __construct(
        #[DefaultValue(provider: ListProvider::class, when: [ValueState::Null, ValueState::Present])]
        #[Nested(discriminator: 'kind', map: ['known' => AddressDto::class])]
        public ?array $items = null,
    ) {
    }
}
