<?php

declare(strict_types=1);

namespace ApiSutra\Localization;

use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Contracts\Interfaces\Localization\LocalizableExceptionInterface;
use Throwable;

/** Явная граница представления, не хранит состояние клиента или процесса. */
final class ExceptionLocalization
{
    public static function apply(Throwable $exception, LocalizationConfig $localization): Throwable
    {
        return $exception instanceof LocalizableExceptionInterface ? $exception->localized($localization) : $exception;
    }

    public static function message(Throwable $exception): string|Message
    {
        return $exception instanceof LocalizableExceptionInterface
            ? $exception->messageDefinition() ?? $exception->getMessage()
            : $exception->getMessage();
    }
}
