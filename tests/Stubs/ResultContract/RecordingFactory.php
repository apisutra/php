<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ResultContract;

use ApiSutra\Contracts\Interfaces\Errors\ExecutionExceptionFactoryInterface;
use ApiSutra\Result\ExecutionResult;
use Throwable;

final class RecordingFactory implements ExecutionExceptionFactoryInterface
{
    /** @var list<ExecutionResult> */
    public array $results = [];
    /** @var list<string> */
    public array $messages = [];

    public function __construct(public bool $fallback = false, public ?Throwable $failure = null, public ?Throwable $returned = null)
    {
    }

    public function make(ExecutionResult $result, string $message): ?Throwable
    {
        $this->results[] = $result;
        $this->messages[] = $message;
        if ($this->failure !== null) {
            throw $this->failure;
        }
        return $this->fallback ? null : ($this->returned ?? new ProviderFailure($message, previous: $result->exception));
    }
}
