<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class OutputAddressDto extends AbstractDto
{
    public function __construct(
        #[To('city')]
        public string $city,
        #[To('zip_code')]
        public string $zipCode,
    ) {}
}
