<?php

declare(strict_types=1);

namespace Example\DtoShowcase;

use ApiSutra\DataTransfer\AbstractDto;

final readonly class TagDto extends AbstractDto
{
    public function __construct(public string $name)
    {
    }
}
