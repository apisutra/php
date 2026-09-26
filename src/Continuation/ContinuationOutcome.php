<?php

declare(strict_types=1);

namespace ApiSutra\Continuation;

use ApiSutra\Result\ExecutionResult;
use ApiSutra\Diagnostics\ExecutionTrace;
use ApiSutra\VO\Audit\PipelineEvent;
use ApiSutra\Serialization\Input\HydrationInput;

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
        /** @internal */
        private ?HydrationInput $input = null,
    ) {
    }

    /** @internal Сохраняется для повторной гидратации, без исходного документа целиком. */
    public function input(): HydrationInput
    {
        return $this->input ?? new HydrationInput($this->payload);
    }
}
