<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Http;

enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';

    /** HTTP-методы, передающие параметры через query string */
    public function isQueryMethod(): bool
    {
        return match ($this) {
            self::GET, self::DELETE => true,
            default => false,
        };
    }
}
