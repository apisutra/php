<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Shapes;

use ApiSutra\Serialization\Rules\ScalarType;
use ApiSutra\Serialization\Rules\HandlerSpec;

final readonly class ListShape implements ShapeSpec
{
    public function __construct(
        public ScalarType|ShapeSpec $item,
        public ?string $each = null,
        public ?HandlerSpec $itemCast = null,
        public bool $normalizeKeys = false,
    ) {
    }
}
