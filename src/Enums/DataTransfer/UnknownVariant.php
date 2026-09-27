<?php

declare(strict_types=1);

namespace ApiSutra\Enums\DataTransfer;

/**
 * Политика обработки неизвестного варианта DTO.
 */
enum UnknownVariant: string
{
    /**
     * Оставить элемент в raw-виде.
     */
    case KeepRaw = 'keep_raw';

    /**
     * Пропустить элемент.
     */
    case Skip = 'skip';

    /**
     * Бросить исключение.
     */
    case Error = 'error';
}
