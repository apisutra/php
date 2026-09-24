<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingMetadata;

use ApiSutra\Attributes\DataTransfer\Extras;

final class LazyInvalid
{
    #[Extras]
    public string $extra = '';
}
