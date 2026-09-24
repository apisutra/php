<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Continuation;

use ApiSutra\Continuation\ContinuationContext;
use ApiSutra\Continuation\ContinuationState;
use ApiSutra\Contracts\Interfaces\Continuation\ContinuationStateResolverInterface;
use ApiSutra\Result\ExecutionResult;
use Throwable;

final class RecordingStateResolver implements ContinuationStateResolverInterface
{
    /** @var list<ContinuationContext> */
    public array $contexts = [];
    /** @var list<ExecutionResult> */
    public array $results = [];
    public ?Throwable $error = null;
    public ?ContinuationState $state = null;

    public function resolve(ExecutionResult $result, ContinuationContext $context): ContinuationState
    {
        $this->contexts[] = $context;
        $this->results[] = $result;
        if ($this->error !== null) {
            throw $this->error;
        }
        if ($this->state !== null) {
            return $this->state;
        }
        $payload = $result->response?->json() ?? $result->data;
        return match ($payload['phase'] ?? null) {
            'ready' => ContinuationState::ready($payload['data'] ?? null, 'data'),
            'failed' => ContinuationState::failed(),
            default => ContinuationState::pending(),
        };
    }
}
