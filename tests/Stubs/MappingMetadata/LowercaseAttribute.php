<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingMetadata;

use apisutra\attributes\datatransfer\from;

final class LowercaseAttribute
{
    #[from('wire_id')]
    public int $id;
}
