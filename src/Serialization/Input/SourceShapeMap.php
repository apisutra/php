<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Input;

use ApiSutra\Serialization\Rules\ContainerShape;

/** @internal Отметки неоднозначных объектов и их предков; без ссылок на значения и родителей. */
final readonly class SourceShapeMap
{
    /** @param array<array-key, self> $children */
    public function __construct(public ContainerShape $kind, public array $children = [])
    {
    }

    /** Отсутствующая отметка допускает вывод по PHP-форме только при известном JSON. */
    public static function kindOf(mixed $value, ?self $shape, bool $known): ?ContainerShape
    {
        if (!$known || !is_array($value)) {
            return null;
        }
        return $shape->kind ?? (array_is_list($value) ? ContainerShape::List : ContainerShape::Object);
    }

    /** @param list<int|string> $segments */
    public function select(array $segments): ?self
    {
        $node = $this;
        foreach ($segments as $segment) {
            $node = $node->children[$segment] ?? null;
            if ($node === null) {
                return null;
            }
        }
        return $node;
    }
}
