<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingMetadata;

use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Shapes\DtoShape;

final class RootValid
{
    #[Shape(new DtoShape(PairA::class))]
    public PairA $checked;

    #[Shape(new DtoShape(LazyValid::class))]
    public object $deferred;
}
