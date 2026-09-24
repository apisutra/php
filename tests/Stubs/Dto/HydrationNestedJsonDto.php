<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Casts\JsonCast;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class HydrationNestedJsonDto extends AbstractDto
{
    /** @param list<mixed> $items */
    public function __construct(#[Nested(each: 'value', itemCast: JsonCast::class)] public array $items) {}
}
