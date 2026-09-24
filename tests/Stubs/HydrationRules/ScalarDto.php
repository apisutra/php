<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

final readonly class ScalarDto
{
    public function __construct(
        public int $int = 1,
        public float $float = 1.0,
        public bool $bool = true,
        public string $string = 'text',
        public true $yes = true,
        public false $no = false,
        public int|string $union = 1,
        public int|float|string $wide = 1,
        public mixed $any = null,
    ) {
    }
}
