<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\Casts\UppercaseCast;

final readonly class OutputUserDto extends AbstractDto
{
    /**
     * @param array<int, OutputItemDto> $items
     */
    public function __construct(
        #[To('user_id')]
        public int $userId,
        #[To('profile')]
        public OutputAddressDto $address,
        #[To('items')]
        public array $items,
        #[To('title')]
        #[Cast(UppercaseCast::class)]
        public string $title,
        #[To('meta.tags')]
        public array $tags = [],
        public ?string $plainValue = null,
    ) {}
}
