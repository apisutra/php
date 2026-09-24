<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingMetadata;

final class StructuralChild extends StructuralParent
{
    private ?parent $hidden = null;
    public string $uninitialized;
}
