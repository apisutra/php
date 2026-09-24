<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderB\Dto;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Casts\EnumCast;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\ProviderB\Enums\ProviderBErrorCode;
use ApiSutra\Tests\Stubs\ProviderB\Enums\ProviderBStatus;

final readonly class ProviderBAsyncResponseDto extends AbstractDto
{
    /**
     * Данные ответа.
     *
     * @param array<string, mixed> $data
     */
    public function __construct(
        #[From('status')]
        #[Cast(EnumCast::class, ProviderBStatus::class)]
        public ProviderBStatus $status,
        #[From('error_code')]
        #[Cast(EnumCast::class, ProviderBErrorCode::class)]
        public ?ProviderBErrorCode $errorCode = null,
        #[From('operation_id')]
        public ?string $operationId = null,
        #[From('data')]
        ?array $data = null,
    ) {
        $this->data = $data ?? [];
    }

    /**
     * Данные ответа.
     *
     * @var array<string, mixed>
     */
    public array $data;
}
