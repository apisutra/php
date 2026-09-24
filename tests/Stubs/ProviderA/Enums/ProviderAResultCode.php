<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderA\Enums;

enum ProviderAResultCode: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case NoData = 'no_data';
}
