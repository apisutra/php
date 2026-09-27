<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Input;

use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Rules\ContainerShape;

/** @internal Известная форма JSON имеет приоритет над неоднозначным PHP-массивом. */
final readonly class InputShapeGuard
{
    public static function assert(mixed $value, ContainerShape $expected, bool $normalizeKeys = false, ?ContainerShape $source = null, bool $emptyListAsObject = false): void
    {
        if ($expected === ContainerShape::Object && $emptyListAsObject && $value === [] && $source === ContainerShape::List) {
            $source = ContainerShape::Object;
        }
        $valid = $source !== null
            ? $source === $expected || $expected === ContainerShape::List && $normalizeKeys
            : ($expected === ContainerShape::List
                ? is_array($value) && ($normalizeKeys || array_is_list($value))
                : is_object($value) || is_array($value) && ($value === [] || !array_is_list($value)));
        if (!$valid) {
            throw HydrationException::invalidValue(
                $expected === ContainerShape::List ? 'invalid_list_shape' : 'invalid_object_shape',
                $expected->value,
                $source->value ?? get_debug_type($value),
            );
        }
    }
}
