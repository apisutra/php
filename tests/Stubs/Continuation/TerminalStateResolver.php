<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Continuation;

use ApiSutra\Continuation\ContinuationContext;
use ApiSutra\Continuation\ContinuationState;
use ApiSutra\Contracts\Interfaces\Continuation\ContinuationStateResolverInterface;
use ApiSutra\Result\ExecutionResult;

final readonly class TerminalStateResolver implements ContinuationStateResolverInterface
{
    public function resolve(ExecutionResult $result, ContinuationContext $context): ContinuationState
    {
        return ContinuationState::failed();
    }
}
