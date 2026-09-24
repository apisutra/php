<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Behavior;

use Attribute;
use ApiSutra\Enums\Execution\ExecutionMode;
use ApiSutra\Enums\Execution\FailStrategy;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Execution
{
    public function __construct(
        public ExecutionMode $mode = ExecutionMode::Sequential,
        public FailStrategy $failStrategy = FailStrategy::FailAll,
    ) {
    }
}
