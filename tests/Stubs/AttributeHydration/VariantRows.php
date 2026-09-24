<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Shapes\ListShape;
use ApiSutra\Serialization\Shapes\VariantsShape;

final readonly class VariantRows
{
    public function __construct(
        #[Shape(new ListShape(new VariantsShape('type', ['row' => Row::class])))]
        public array $values,
        #[Extras] public array $_extra = [],
    ) {
    }
}
