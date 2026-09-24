<?php

declare(strict_types=1);

namespace Example\DtoHydrator;

use ApiSutra\DataTransfer\AbstractDto;

final readonly class Address extends AbstractDto
{
    public function __construct(public string $city) {}
}
