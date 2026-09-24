<?php

declare(strict_types=1);

namespace ApiSutra\Execution;

use Throwable;

/** @internal Итог обхода после завершения выданных исполнений. Не содержит результатов. */
final readonly class ExecutionOutcome
{
    /** @param 'source'|'executor'|'handler'|null $failureStage */
    public function __construct(
        public bool $sourceExhausted = false,
        public bool $stoppedOnFailure = false,
        public ?Throwable $failure = null,
        public ?string $failureStage = null,
    ) {
    }
}
