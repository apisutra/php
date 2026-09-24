<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderB;

use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Tests\Stubs\ProviderB\Enums\ProviderBErrorCode;
use ApiSutra\Tests\Stubs\ProviderB\Enums\ProviderBStatus;

final class ProviderBStatusMap
{
    public static function mapStatus(ProviderBStatus $status): ResultStatus
    {
        return match ($status) {
            ProviderBStatus::Ready => ResultStatus::SUCCESS,
            ProviderBStatus::Accepted,
            ProviderBStatus::InProgress,
            ProviderBStatus::PaymentRequired,
            ProviderBStatus::Suspended => ResultStatus::PARTIAL,
            ProviderBStatus::Failed,
            ProviderBStatus::NotFound,
            ProviderBStatus::ValidationError,
            ProviderBStatus::Rejected,
            ProviderBStatus::Timeout,
            ProviderBStatus::Canceled => ResultStatus::FAILED,
        };
    }

    public static function mapError(ProviderBErrorCode $code): ResultStatus
    {
        return match ($code) {
            ProviderBErrorCode::InvalidInput => ResultStatus::FAILED,
            ProviderBErrorCode::NotFound => ResultStatus::FAILED,
            ProviderBErrorCode::ProviderError => ResultStatus::FAILED,
            ProviderBErrorCode::LimitExceeded => ResultStatus::FAILED,
        };
    }
}
