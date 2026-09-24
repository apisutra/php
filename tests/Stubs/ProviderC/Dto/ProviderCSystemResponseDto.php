<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderC\Dto;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Casts\EnumCast;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\ProviderC\Enums\ProviderCSystemStatus;

final readonly class ProviderCSystemResponseDto extends AbstractDto
{
    public function __construct(
        #[From('status')]
        #[Cast(EnumCast::class, ProviderCSystemStatus::class)]
        public ProviderCSystemStatus $status,
        #[From('query_type')]
        public int $queryType,
        #[From('uuid')]
        public string $uuid,
    ) {}
}
