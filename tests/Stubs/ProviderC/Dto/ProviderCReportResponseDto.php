<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderC\Dto;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Casts\EnumCast;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\ProviderC\Enums\ProviderCReportStatus;

final readonly class ProviderCReportResponseDto extends AbstractDto
{
    /**
     * @param array<string, mixed> $response
     */
    public function __construct(
        #[From('status')]
        #[Cast(EnumCast::class, ProviderCReportStatus::class)]
        public ProviderCReportStatus $status,
        #[From('waitTime')]
        public ?int $waitTime = null,
        #[From('response')]
        ?array $response = null,
    ) {
        $this->response = $response ?? [];
    }

    /**
     * Данные отчёта.
     *
     * @var array<string, mixed>
     */
    public array $response;
}
