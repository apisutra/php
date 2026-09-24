<?php

declare(strict_types=1);

namespace Example\DtoShowcase;

use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;
use ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Exceptions\Serialization\SerializationException;

// Учебный формат: неотрицательная сумма, до семи цифр перед точкой и ровно две после.
final readonly class MinorUnitsCast implements CastInterface
{
    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        if (!is_string($value) || preg_match('/^([0-9]{1,7})\.([0-9]{2})$/D', $value, $parts) !== 1) {
            throw HydrationException::invalidValue(
                'invalid_price',
                'decimal string with 2 digits',
                get_debug_type($value),
            );
        }

        // Целочисленная арифметика сохраняет точность исходной строки.
        return (int) $parts[1] * 100 + (int) $parts[2];
    }

    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        if (!is_int($value) || $value < 0 || $value > 999_999_999) {
            throw new SerializationException('Ожидается целое число в диапазоне учебного формата цены');
        }

        return intdiv($value, 100) . '.' . str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }
}
