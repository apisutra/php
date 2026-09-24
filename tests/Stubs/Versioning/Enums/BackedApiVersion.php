<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Versioning\Enums;

enum BackedApiVersion: string
{
    case V2 = 'v2';
    case V3 = 'v3';
}
