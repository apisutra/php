<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderA\Enums;

enum ProviderAErrorCode: string
{
    case InvalidInput = 'invalid_input';
    case NotFound = 'not_found';
    case ProviderError = 'provider_error';
    case Timeout = 'timeout';
}
