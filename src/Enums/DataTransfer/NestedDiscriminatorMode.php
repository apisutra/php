<?php

declare(strict_types=1);

namespace ApiSutra\Enums\DataTransfer;

/**
 * Режим извлечения discriminator для Nested.
 */
enum NestedDiscriminatorMode: string
{
    /**
     * Discriminator извлекается как значение поля по пути.
     */
    case Value = 'value';

    /**
     * Discriminator извлекается как имя ключа.
     */
    case Key = 'key';
}
