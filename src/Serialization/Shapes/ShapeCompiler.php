<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Shapes;

use ApiSutra\Localization\Message;
use ApiSutra\Serialization\Rules\ScalarType;
use ApiSutra\Serialization\Rules\ValueShape;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

/** @internal Только адаптер синтаксиса; правила значений принадлежат ValueShape. */
final class ShapeCompiler
{
    public function compile(ScalarType|ShapeSpec $shape): ValueShape
    {
        return match (true) {
            $shape instanceof ScalarType => ValueShape::scalars($shape),
            $shape instanceof ListShape => ValueShape::list($this->compile($shape->item), $shape->each, $shape->itemCast, $shape->normalizeKeys),
            $shape instanceof NullableShape => ValueShape::nullable($this->compile($shape->value)),
            $shape instanceof DtoShape => ValueShape::dto($shape->class, $shape->emptyListAsObject),
            $shape instanceof VariantsShape => ValueShape::variants($shape->discriminator, $shape->map, $shape->mode, $shape->unknown),
            default => throw new ConfigurationException(new Message('serialization.shape_accepts_only_built_in_shape_nodes')),
        };
    }
}
