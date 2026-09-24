<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderA\Dto;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Casts\EnumCast;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\ProviderA\Enums\ProviderAErrorCode;
use ApiSutra\Tests\Stubs\ProviderA\Enums\ProviderAResultCode;

final readonly class ProviderASyncResponseDto extends AbstractDto
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        #[From('result_code')]
        #[Cast(EnumCast::class, ProviderAResultCode::class)]
        public ProviderAResultCode $resultCode,
        #[From('error_code')]
        #[Cast(EnumCast::class, ProviderAErrorCode::class)]
        public ?ProviderAErrorCode $errorCode = null,
        #[From('operation_token')]
        public ?string $operationToken = null,
        #[From('data')]
        public array $data = [],
    ) {}
}
