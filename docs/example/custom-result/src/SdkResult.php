<?php

declare(strict_types=1);

namespace Example\CustomResult;

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResolvedResultInterface;
use ApiSutra\VO\Errors\ClientError;
use Example\ClientShowcase\Resources\Records\RecordDto;
use Override;
use UnexpectedValueException;

// Обёртка добавляет методы SDK и сохраняет стандартное представление ошибок.
final readonly class SdkResult implements ResolvedResultInterface
{
    public function __construct(private ResolvedResultInterface $inner)
    {
    }

    public function recordOrFail(): RecordDto
    {
        $this->inner->result()->throw();
        $data = $this->inner->data();
        if (!$data instanceof RecordDto) {
            throw new UnexpectedValueException('Эта операция не вернула RecordDto');
        }

        return $data;
    }

    public function requiresReauthorization(): bool
    {
        return $this->inner->errorStatus() === 401;
    }

    #[Override]
    public function data(): mixed
    {
        return $this->inner->data();
    }

    #[Override]
    public function isSuccess(): bool
    {
        return $this->inner->isSuccess();
    }

    #[Override]
    public function isPartial(): bool
    {
        return $this->inner->isPartial();
    }

    #[Override]
    public function isFailed(): bool
    {
        return $this->inner->isFailed();
    }

    #[Override]
    public function hasData(): bool
    {
        return $this->inner->hasData();
    }

    #[Override]
    public function hasErrors(): bool
    {
        return $this->inner->hasErrors();
    }

    #[Override]
    public function errors(): ErrorCollection
    {
        return $this->inner->errors();
    }

    /** @return array<int, ClientError> */
    #[Override]
    public function errorViews(): array
    {
        return $this->inner->errorViews();
    }

    #[Override]
    public function error(): ?ClientError
    {
        return $this->inner->error();
    }

    /** @return array<int, object|null> */
    #[Override]
    public function errorContexts(): array
    {
        return $this->inner->errorContexts();
    }

    #[Override]
    public function errorContext(): ?object
    {
        return $this->inner->errorContext();
    }

    #[Override]
    public function errorCode(): ?string
    {
        return $this->inner->errorCode();
    }

    #[Override]
    public function errorMessage(): ?string
    {
        return $this->inner->errorMessage();
    }

    #[Override]
    public function errorStatus(): ?int
    {
        return $this->inner->errorStatus();
    }

    #[Override]
    public function errorRetryable(): ?bool
    {
        return $this->inner->errorRetryable();
    }

    #[Override]
    public function errorCategory(): ?string
    {
        return $this->inner->errorCategory();
    }

    #[Override]
    public function errorProviderTraceId(): ?string
    {
        return $this->inner->errorProviderTraceId();
    }

    #[Override]
    public function continuationToken(): ?string
    {
        return $this->inner->continuationToken();
    }

    #[Override]
    public function continuationTokenOrFail(): string
    {
        return $this->inner->continuationTokenOrFail();
    }

    #[Override]
    public function message(): ?string
    {
        return $this->inner->message();
    }

    #[Override]
    public function result(): ExecutionResult
    {
        return $this->inner->result();
    }
}
