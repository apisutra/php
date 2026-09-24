<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

use ApiSutra\Exceptions\Serialization\HydrationException;
use ReflectionFunction;
use ReflectionProperty;
use TypeError;

/** @internal Последний шаг Legacy-присваивания без создания или изменения DTO. */
final readonly class NativePropertyValue
{
    public function __construct(private PropertyTypeInspector $types = new PropertyTypeInspector())
    {
    }

    public function resolve(mixed $value, ReflectionProperty $property): mixed
    {
        if ($value === null) {
            return null;
        }
        $mask = 0;
        foreach ($this->types->getPropertyTypes($property) as $type) {
            if ($this->types->matchesRuntimeType($value, $type)) {
                return $value;
            }
            $mask |= match ($type) {
                'int' => 1, 'float' => 2, 'string' => 4, 'bool' => 8, default => 0,
            };
        }
        // Reflection выполняет те же слабые scalar conversions, что и setValue,
        // включая числовые строки и union. Преобразования кастера уже выполнены.
        $coerce = match ($mask) {
            1 => static fn (int $v): int => $v,
            2 => static fn (float $v): float => $v,
            3 => static fn (int|float $v): int|float => $v,
            4 => static fn (string $v): string => $v,
            5 => static fn (int|string $v): int|string => $v,
            6 => static fn (float|string $v): float|string => $v,
            7 => static fn (int|float|string $v): int|float|string => $v,
            8 => static fn (bool $v): bool => $v,
            9 => static fn (int|bool $v): int|bool => $v,
            10 => static fn (float|bool $v): float|bool => $v,
            11 => static fn (int|float|bool $v): int|float|bool => $v,
            12 => static fn (string|bool $v): string|bool => $v,
            13 => static fn (int|string|bool $v): int|string|bool => $v,
            14 => static fn (float|string|bool $v): float|string|bool => $v,
            15 => static fn (int|float|string|bool $v): int|float|string|bool => $v,
            default => null,
        };
        try {
            if ($coerce !== null) {
                return (new ReflectionFunction($coerce))->invoke($value);
            }
        } catch (TypeError) {
            // Не сохраняем сообщение движка: оно может содержать входные данные.
        }
        throw HydrationException::invalidValue('invalid_field_type', (string) $property->getType(), get_debug_type($value));
    }
}
