<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingMetadata;

use ApiSutra\Attributes\DataTransfer\Map;

class StructuralParent
{
    public static int $counter = 0;
    protected ?self $ancestor = null;

    public function __construct(#[Map('wire_id')] public int $id = 0)
    {
    }
}
