<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

/** Проверка диапазона до преобразования, без потери цифр целочисленной строки. */
final class IntegerRange
{
    public static function overflows(mixed $value): bool
    {
        if (is_string($value)) {
            $value = trim($value);
            if (preg_match('/^[+-]?\d+$/D', $value) === 1) {
                $negative = str_starts_with($value, '-');
                $digits = ltrim(ltrim($value, '+-'), '0');
                $limit = $negative ? substr((string) PHP_INT_MIN, 1) : (string) PHP_INT_MAX;

                return strlen($digits) > strlen($limit)
                    || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0);
            }
            if (!is_numeric($value)) {
                return false;
            }
            $value = (float) $value;
        }

        // Верхняя граница исключающая: float(PHP_INT_MAX) на 64-bit округляется вверх.
        return is_float($value) && (!is_finite($value)
            || $value < (float) PHP_INT_MIN
            || $value >= -(float) PHP_INT_MIN);
    }
}
