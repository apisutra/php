<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Enums\DataTransfer\ValueState;

final readonly class FallbackDefaultDto extends AbstractDto
{
    /**
     * @param array<int, NestedItemDto> $items
     */
    public function __construct(
        #[From('primary', fallback: ['secondary'])]
        public ?string $name = null,
        #[From('status')]
        #[DefaultValue(value: 'unknown', when: [ValueState::Missing, ValueState::Null])]
        public string $status = 'initial',
        #[From('code')]
        #[DefaultValue(value: 'fallback', when: [ValueState::Null])]
        public ?string $code = 'code-default',
        #[From('provided')]
        #[DefaultValue(provider: StatusDefaultProvider::class, when: [ValueState::Missing, ValueState::Null])]
        public string $provided = 'local',
        #[Nested(type: NestedItemDto::class, from: 'items', fallback: ['alt_items'])]
        public array $items = [],
    ) {}
}
