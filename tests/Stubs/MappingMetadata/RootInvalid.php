<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingMetadata;

use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Shapes\DtoShape;

final class RootInvalid
{
    #[Shape(new DtoShape(PairA::class))]
    public PairA $checked;

    #[Shape(new DtoShape(LazyInvalid::class))]
    public object $deferred;
}
