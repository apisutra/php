<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Execution;

use ApiSutra\Continuation\ContinuationContext;
use ApiSutra\Continuation\ContinuationState;
use ApiSutra\Contracts\Interfaces\Continuation\ContinuationStateResolverInterface;
use ApiSutra\Result\ExecutionResult;

final class PollResolver implements ContinuationStateResolverInterface
{
    /** @var list<int|null> */
    public array $statuses = [];

    public function resolve(ExecutionResult $result, ContinuationContext $context): ContinuationState
    {
        $this->statuses[] = $result->response?->status;
        $data = $result->response?->json() ?? [];
        return ($data['phase'] ?? null) === 'ready'
            ? ContinuationState::ready($data['data'], 'data')
            : ContinuationState::pending();
    }
}
