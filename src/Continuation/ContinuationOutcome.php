<?php

declare(strict_types=1);

namespace ApiSutra\Continuation;

use ApiSutra\Result\ExecutionResult;
use ApiSutra\Diagnostics\ExecutionTrace;
use ApiSutra\VO\Audit\PipelineEvent;

final readonly class ContinuationOutcome
{
    public function __construct(
        public mixed $value,
        public mixed $payload,
        public ?string $path,
        public ExecutionResult $lastResult,
        public int $attempts,
        public ?ExecutionTrace $trace = null,
        /** @var list<PipelineEvent> */
        public array $audit = [],
    ) {
    }
}
