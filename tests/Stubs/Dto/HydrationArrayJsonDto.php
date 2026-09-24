<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Casts\JsonCast;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class HydrationArrayJsonDto extends AbstractDto
{
    /** @param array<array-key, mixed> $payload */
    public function __construct(#[Cast(JsonCast::class)] public array $payload) {}
}
