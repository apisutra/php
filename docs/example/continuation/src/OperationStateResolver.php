<?php

declare(strict_types=1);

namespace Example\Continuation;

use ApiSutra\Continuation\ContinuationContext;
use ApiSutra\Continuation\ContinuationState;
use ApiSutra\Contracts\Interfaces\Continuation\ContinuationStateResolverInterface;
use ApiSutra\Result\ExecutionResult;

final readonly class OperationStateResolver implements ContinuationStateResolverInterface
{
    public function resolve(ExecutionResult $result, ContinuationContext $context): ContinuationState
    {
        $data = $result->response?->json();

        return match (is_array($data) ? ($data['status'] ?? null) : null) {
            'done' => ContinuationState::ready($data['data'] ?? null, 'data'),
            'failed' => ContinuationState::failed(),
            default => ContinuationState::pending(),
        };
    }
}
