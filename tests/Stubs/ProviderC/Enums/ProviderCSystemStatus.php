<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderC\Enums;

enum ProviderCSystemStatus: int
{
    case Ok = 0;
    case TariffInactive = -1;
    case TariffExpired = -2;
    case InsufficientFunds = -3;
    case CreditExpired = -4;
    case PlanRestricted = -5;
}
