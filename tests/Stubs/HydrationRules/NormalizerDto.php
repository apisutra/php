<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use ApiSutra\Attributes\DataTransfer\Cast;

final readonly class NormalizerDto
{
    public function __construct(
        #[EmptyStringAsNull] public ?string $empty = null,
        #[Cast(ReturnCast::class, 'cast')] public ?string $cast = null,
        public ?string $plain = null,
    ) {
    }
}
