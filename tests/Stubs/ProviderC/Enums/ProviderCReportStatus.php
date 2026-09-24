<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderC\Enums;

enum ProviderCReportStatus: int
{
    case Waiting = 0;
    case Ready = 1;
    case TariffInactive = -1;
    case TariffExpired = -2;
    case InsufficientFunds = -3;
    case CreditExpired = -4;
    case PlanRestricted = -5;
}
