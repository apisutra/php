<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use UnitEnum;

/** @internal Проверка неизменяемых аргументов декларации без хранения объектов исполнения. */
final readonly class LiteralValues
{
    public static function assert(mixed $value, int $depth = 0): void
    {
        if ($depth > 512) {
            throw new ConfigurationException(new Message('serialization.declaration_value_is_too_deep_or_recursive'));
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                self::assert($item, $depth + 1);
            }
            return;
        }
        if ($value !== null && !is_scalar($value) && !$value instanceof UnitEnum) {
            throw new ConfigurationException(new Message('serialization.declaration_accepts_only_scalar_null_enum_and_arrays_of'));
        }
    }
}
