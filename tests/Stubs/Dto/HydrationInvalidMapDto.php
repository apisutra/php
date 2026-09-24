<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class HydrationInvalidMapDto extends AbstractDto
{
    /** @param list<mixed> $items */
    public function __construct(#[Nested(discriminator: 'kind', map: ['known' => 'FixtureMissingDto'])] public array $items) {}
}
