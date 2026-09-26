<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Input;

use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Rules\InputShape;

/** @internal Известная форма JSON имеет приоритет над неоднозначным PHP-массивом. */
final readonly class InputShapeGuard
{
    public static function assert(mixed $value, InputShape $expected, bool $normalizeKeys = false, ?InputShape $source = null, bool $emptyListAsObject = false): void
    {
        if ($expected === InputShape::Object && $emptyListAsObject && $value === [] && $source === InputShape::List) {
            $source = InputShape::Object;
        }
        $valid = $source !== null
            ? $source === $expected || $expected === InputShape::List && $normalizeKeys
            : ($expected === InputShape::List
                ? is_array($value) && ($normalizeKeys || array_is_list($value))
                : is_object($value) || is_array($value) && ($value === [] || !array_is_list($value)));
        if (!$valid) {
            throw HydrationException::invalidValue(
                $expected === InputShape::List ? 'invalid_list_shape' : 'invalid_object_shape',
                $expected->value,
                $source->value ?? get_debug_type($value),
            );
        }
    }
}
