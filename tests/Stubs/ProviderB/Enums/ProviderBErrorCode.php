<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderB\Enums;

enum ProviderBErrorCode: string
{
    case InvalidInput = 'invalid_input';
    case NotFound = 'not_found';
    case ProviderError = 'provider_error';
    case LimitExceeded = 'limit_exceeded';
}
