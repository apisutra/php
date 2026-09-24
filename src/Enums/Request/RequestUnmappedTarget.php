<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Request;

/**
 * Целевой раздел для неразмеченных свойств запроса.
 */
enum RequestUnmappedTarget: string
{
    /**
     * Стандартное поведение по HTTP-методу:
     * GET/DELETE -> query, остальные -> body.
     */
    case Convention = 'convention';

    /**
     * Все неразмеченные свойства сериализуются в query.
     */
    case Query = 'query';

    /**
     * Все неразмеченные свойства сериализуются в body.
     */
    case Body = 'body';
}
