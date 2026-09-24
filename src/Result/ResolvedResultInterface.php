<?php

declare(strict_types=1);

namespace ApiSutra\Result;

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\VO\Errors\ClientError;

/**
 * Контракт результата для использования в приложениях.
 */
interface ResolvedResultInterface
{
    public function data(): mixed;

    public function isSuccess(): bool;

    public function isPartial(): bool;

    public function isFailed(): bool;

    public function hasData(): bool;

    public function hasErrors(): bool;

    public function errors(): ErrorCollection;

    /**
     * @return array<int, ClientError>
     */
    public function errorViews(): array;

    public function error(): ?ClientError;

    /**
     * @return array<int, object|null>
     */
    public function errorContexts(): array;

    public function errorContext(): ?object;

    public function errorCode(): ?string;

    public function errorMessage(): ?string;

    public function errorStatus(): ?int;

    public function errorRetryable(): ?bool;

    public function errorCategory(): ?string;

    public function errorProviderTraceId(): ?string;

    public function continuationToken(): ?string;

    public function continuationTokenOrFail(): string;

    public function message(): ?string;

    public function result(): ExecutionResult;
}
