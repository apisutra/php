<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

use ApiSutra\Exceptions\Serialization\HydrationException;
use Stringable;

final readonly class SafeScalarHydrationCaster
{
    public function canHydrate(string $type, mixed $value): bool
    {
        return match ($type) {
            'int' => $this->canHydrateInt($value),
            'float' => $this->canHydrateFloat($value),
            'bool' => $this->canHydrateBool($value),
            'string' => $this->canHydrateString($value),
            default => false,
        };
    }

    public function hydrate(string $type, mixed $value): mixed
    {
        return match ($type) {
            'int' => $this->hydrateInt($value),
            'float' => $this->hydrateFloat($value),
            'bool' => $this->hydrateBool($value),
            'string' => $this->hydrateString($value),
            default => $value,
        };
    }

    private function canHydrateInt(mixed $value): bool
    {
        return is_int($value)
            || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1 && !IntegerRange::overflows($value));
    }

    private function hydrateInt(mixed $value): int
    {
        if (IntegerRange::overflows($value)) {
            throw HydrationException::invalidValue('integer_out_of_range', 'int', get_debug_type($value));
        }
        return is_int($value) ? $value : (int) $value;
    }

    private function canHydrateFloat(mixed $value): bool
    {
        return is_int($value)
            || is_float($value)
            || (is_string($value) && is_numeric($value));
    }

    private function hydrateFloat(mixed $value): float
    {
        return (float) $value;
    }

    private function canHydrateBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return true;
        }

        if (is_int($value)) {
            return $value === 0 || $value === 1;
        }

        if (!is_string($value)) {
            return false;
        }

        return in_array(strtolower($value), ['0', '1', 'true', 'false'], true);
    }

    private function hydrateBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return in_array(strtolower((string) $value), ['1', 'true'], true);
    }

    private function canHydrateString(mixed $value): bool
    {
        return is_string($value)
            || is_int($value)
            || is_float($value)
            || $value instanceof Stringable;
    }

    private function hydrateString(mixed $value): string
    {
        return (string) $value;
    }
}
