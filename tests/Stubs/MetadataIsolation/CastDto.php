<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MetadataIsolation;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class CastDto extends AbstractDto
{
    public function __construct(
        #[Cast(CountingCast::class, new MutableCounter())]
        public int $number = 0,
    ) {
    }
}
