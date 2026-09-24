<?php

declare(strict_types=1);

namespace ApiSutra\VO\Errors;

/**
 * Системные ключи error context.
 */
enum SystemErrorContextKeys: string
{
    case TraceId = 'traceId';
    case HttpStatus = 'httpStatus';
    case RequestClass = 'requestClass';
    case ProviderCode = 'providerCode';
}
