<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderA;

use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Tests\Stubs\ProviderA\Enums\ProviderAErrorCode;
use ApiSutra\Tests\Stubs\ProviderA\Enums\ProviderAResultCode;

final class ProviderAStatusMap
{
    public static function mapResult(ProviderAResultCode $code): ResultStatus
    {
        return match ($code) {
            ProviderAResultCode::Ok => ResultStatus::SUCCESS,
            ProviderAResultCode::Warning => ResultStatus::PARTIAL,
            ProviderAResultCode::NoData => ResultStatus::SUCCESS,
        };
    }

    public static function mapError(ProviderAErrorCode $code): ResultStatus
    {
        return match ($code) {
            ProviderAErrorCode::InvalidInput => ResultStatus::FAILED,
            ProviderAErrorCode::NotFound => ResultStatus::FAILED,
            ProviderAErrorCode::ProviderError => ResultStatus::FAILED,
            ProviderAErrorCode::Timeout => ResultStatus::FAILED,
        };
    }
}
