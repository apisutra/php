<?php

declare(strict_types=1);

namespace ApiSutra\Config;

final readonly class PoolConfig
{
    public function __construct(
        public int $concurrency = 5,
        public bool $stopOnFailure = false,
    ) {
    }
}
