<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingMetadata;

use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Shapes\DtoShape;
use ApiSutra\Serialization\Shapes\NullableShape;

final class PairA
{
    public function __construct(
        #[Shape(new NullableShape(new DtoShape(PairB::class)))]
        public ?PairB $next = null,
    ) {
    }
}
