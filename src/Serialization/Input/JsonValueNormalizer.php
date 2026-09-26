<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Input;

use ApiSutra\Serialization\Rules\InputShape;
use stdClass;

/** @internal Нормализует дерево штатного decode, сохраняя только неоднозначные объекты. */
final readonly class JsonValueNormalizer
{
    public function normalize(mixed &$value): ?SourceShapeMap
    {
        $object = $value instanceof stdClass;
        if ($object) {
            // Замена освобождает объект до обхода детей, без второй полной копии дерева.
            $value = (array) $value;
            if ($value === []) {
                // Как у assoc decode: пустые значения разделяют неизменяемый пустой массив.
                $value = [];
                return new SourceShapeMap(InputShape::Object);
            }
        }
        if (!is_array($value)) {
            return null;
        }
        $ambiguousObject = $object && array_is_list($value);
        $children = [];
        if ($object) {
            foreach (array_keys($value) as $key) {
                if (is_array($value[$key]) || $value[$key] instanceof stdClass) {
                    $child = $this->normalize($value[$key]);
                    if ($child !== null) {
                        $children[$key] = $child;
                    }
                }
            }
        } else {
            for ($index = 0, $count = count($value); $index < $count; $index++) {
                if (is_array($value[$index]) || $value[$index] instanceof stdClass) {
                    $child = $this->normalize($value[$index]);
                    if ($child !== null) {
                        $children[$index] = $child;
                    }
                }
            }
        }
        return $ambiguousObject || $children !== []
            ? new SourceShapeMap($object ? InputShape::Object : InputShape::List, $children) : null;
    }
}
