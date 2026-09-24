<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class OutputNullArrayDto extends AbstractDto
{
    /**
     * @param array<int, string|null> $items Массив значений, допускающий null.
     */
    public function __construct(
        #[To('items')]
        public array $items,
    ) {}
}
