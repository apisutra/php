<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

use ApiSutra\Localization\Message;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ReflectionProperty;
use UnitEnum;

/** @internal Область значений и сравнение; состояние конкретного DTO сюда не кешируется. */
final readonly class ConstructorValues
{
    public function assertInput(mixed $value): void
    {
        $reason = $this->invalidReason($value);
        if ($reason !== null) {
            throw HydrationException::invalidValue($reason, 'scalar|enum|null|array (depth <= 512)', get_debug_type($value));
        }
    }

    public function check(object $dto, ReflectionProperty $property, HydratedProperty $input): void
    {
        $name = $property->getName();
        if (!$property->isInitialized($dto)) {
            throw new ConfigurationException(new Message('serialization.constructor_did_not_initialize_constructorvalue', ['value0' => $dto::class, 'name' => $name]));
        }
        $expected = $property->getValue($dto);
        // Проверяем всё значение до сравнения, даже при Missing или разной длине.
        if ($this->invalidReason($expected) !== null) {
            throw new ConfigurationException(new Message('serialization.unsupported_constructorvalue', ['value0' => $dto::class, 'name' => $name]));
        }
        if ($input->state !== ValueState::Missing && !$this->equal($expected, $input->value)) {
            throw HydrationException::invalidValue(
                'constructor_value_mismatch',
                get_debug_type($expected),
                get_debug_type($input->value),
                $name,
            );
        }
    }

    private function invalidReason(mixed $value, int $depth = 0): ?string
    {
        if (is_array($value)) {
            if ($depth >= 512) {
                return 'hydration_depth_exceeded';
            }
            foreach ($value as $item) {
                $reason = $this->invalidReason($item, $depth + 1);
                if ($reason !== null) {
                    return $reason;
                }
            }
            return null;
        }
        return $value === null || is_scalar($value) || $value instanceof UnitEnum ? null : 'invalid_field_type';
    }

    private function equal(mixed $expected, mixed $actual): bool
    {
        if (!is_array($expected) || !is_array($actual)) {
            return $expected === $actual;
        }
        if (count($expected) !== count($actual)) {
            return false;
        }
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $actual) || !$this->equal($value, $actual[$key])) {
                return false;
            }
        }
        return true;
    }
}
