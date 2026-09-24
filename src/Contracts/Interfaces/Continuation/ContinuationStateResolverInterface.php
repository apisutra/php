<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Continuation;

use ApiSutra\Continuation\ContinuationContext;
use ApiSutra\Continuation\ContinuationState;
use ApiSutra\Result\ExecutionResult;

interface ContinuationStateResolverInterface
{
    /** Определяет состояние протокола без I/O и создания финального DTO. */
    public function resolve(ExecutionResult $result, ContinuationContext $context): ContinuationState;
}
