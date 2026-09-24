<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Request;

/**
 * Режим валидации oneOf-группы в запросе.
 */
enum OneOfMode: string
{
    /**
     * Должен быть заполнен ровно один вариант.
     */
    case ExactlyOne = 'exactly_one';

    /**
     * Должен быть заполнен хотя бы один вариант.
     */
    case AtLeastOne = 'at_least_one';
}
