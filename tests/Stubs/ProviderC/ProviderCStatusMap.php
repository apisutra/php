<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderC;

use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Tests\Stubs\ProviderC\Enums\ProviderCReportStatus;
use ApiSutra\Tests\Stubs\ProviderC\Enums\ProviderCSystemStatus;

final class ProviderCStatusMap
{
    public static function mapSystem(ProviderCSystemStatus $status): ResultStatus
    {
        return $status === ProviderCSystemStatus::Ok
            ? ResultStatus::SUCCESS
            : ResultStatus::FAILED;
    }

    public static function mapReport(ProviderCReportStatus $status, ?int $waitTime): ResultStatus
    {
        if ($status === ProviderCReportStatus::Ready) {
            return ResultStatus::SUCCESS;
        }

        if ($status === ProviderCReportStatus::Waiting && $waitTime !== null) {
            return ResultStatus::PARTIAL;
        }

        return ResultStatus::FAILED;
    }
}
