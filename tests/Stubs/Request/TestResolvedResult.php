<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Request;

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResolvedResultInterface;
use ApiSutra\VO\Errors\ClientError;
use RuntimeException;

final readonly class TestResolvedResult implements ResolvedResultInterface
{
    public function __construct(
        private ExecutionResult $result,
    ) {}

    public function data(): mixed
    {
        return $this->result->data;
    }

    public function isSuccess(): bool
    {
        return $this->result->isSuccess();
    }

    public function isPartial(): bool
    {
        return $this->result->isPartial();
    }

    public function isFailed(): bool
    {
        return $this->result->isFailed();
    }

    public function hasData(): bool
    {
        return $this->result->hasData();
    }

    public function hasErrors(): bool
    {
        return $this->result->hasErrors();
    }

    public function errors(): ErrorCollection
    {
        return $this->result->errors;
    }

    public function errorViews(): array
    {
        return [];
    }

    public function error(): ?ClientError
    {
        return null;
    }

    public function errorContexts(): array
    {
        return [];
    }

    public function errorContext(): ?object
    {
        return null;
    }

    public function errorCode(): ?string
    {
        return null;
    }

    public function errorMessage(): ?string
    {
        return null;
    }

    public function errorStatus(): ?int
    {
        return null;
    }

    public function errorRetryable(): ?bool
    {
        return null;
    }

    public function errorCategory(): ?string
    {
        return null;
    }

    public function errorProviderTraceId(): ?string
    {
        return null;
    }

    public function continuationToken(): ?string
    {
        return null;
    }

    public function continuationTokenOrFail(): string
    {
        throw new RuntimeException('Continuation token отсутствует в TestResolvedResult');
    }

    public function message(): ?string
    {
        return $this->result->message();
    }

    public function result(): ExecutionResult
    {
        return $this->result;
    }

    public function marker(): string
    {
        return 'custom';
    }
}
