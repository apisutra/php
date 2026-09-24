<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MetadataIsolation;

use ApiSutra\Attributes\DataTransfer\Cast;

final readonly class CreatedCastDto
{
    public function __construct(
        #[Cast(CreatedCast::class, ['nested' => [new CreatedValue()]])]
        public ?int $number = null,
    ) {
    }
}
