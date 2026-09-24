<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderC\Enums;

enum ProviderCReportEvent: string
{
    case RoleData = 'role-data';
    case RolePreview = 'role-preview';
    case RoleHistory = 'role-history';
}
