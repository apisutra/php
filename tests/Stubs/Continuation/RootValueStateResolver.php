<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Continuation;

use ApiSutra\Continuation\ContinuationContext;
use ApiSutra\Continuation\ContinuationState;
use ApiSutra\Contracts\Interfaces\Continuation\ContinuationStateResolverInterface;
use ApiSutra\Result\ExecutionResult;

final readonly class RootValueStateResolver implements ContinuationStateResolverInterface
{
    public function resolve(ExecutionResult $result, ContinuationContext $context): ContinuationState
    {
        $payload = $result->response?->json();
        return is_array($payload) && array_key_exists('value', $payload)
            ? ContinuationState::ready($payload)
            : ContinuationState::pending();
    }
}
