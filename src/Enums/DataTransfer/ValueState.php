<?php

declare(strict_types=1);

namespace ApiSutra\Enums\DataTransfer;

/**
 * Состояние значения при извлечении из ответа.
 */
enum ValueState: string
{
    case Missing = 'missing';
    case Null = 'null';
    case Present = 'present';
}
