<?php

declare(strict_types=1);

namespace ApiSutra\Config;

use ApiSutra\Enums\Execution\ExecutionMode;
use ApiSutra\Enums\Execution\FailStrategy;

final readonly class BatchConfig
{
    public function __construct(
        public ExecutionMode $mode = ExecutionMode::Sequential,
        public FailStrategy $failStrategy = FailStrategy::FailAll,
        public int $concurrency = 5,
    ) {
    }
}
