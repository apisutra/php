<?php

declare(strict_types=1);

namespace ApiSutra\Traits;

use ApiSutra\Localization\Message;
use ApiSutra\Attributes\Behavior\Cache;
use ApiSutra\Attributes\Behavior\Execution;
use ApiSutra\Attributes\Behavior\Idempotent;
use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Attributes\Behavior\RateLimit;
use ApiSutra\Attributes\Behavior\Cooldown;
use ApiSutra\Attributes\Behavior\Retry;
use ApiSutra\Attributes\Behavior\Timeout;
use ApiSutra\Attributes\Request\AuthScope;
use ApiSutra\Attributes\Request\OperationDescriptor;
use ApiSutra\Attributes\Response\ContinuationResult;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

/**
 * Доступ к атрибутам RequestSpec.
 */
trait RequestSpecAccessorsTrait
{
    #[\Override]
    public function getMethod(): HttpMethod
    {
        $method = $this->spec()->method;
        if ($method === null) {
            throw new ConfigurationException(new Message('traits.http_method_is_not_specified'));
        }

        return $method;
    }

    #[\Override]
    public function getEndpoint(): string
    {
        $resolved = $this->resolveEndpoint();
        if ($resolved !== null) {
            return $resolved;
        }

        $endpoint = $this->spec()->endpoint;
        if ($endpoint === null) {
            throw new ConfigurationException(new Message('traits.endpoint_is_not_specified'));
        }

        return $endpoint;
    }

    #[\Override]
    public function getResponseType(): ?string
    {
        return $this->spec()->responseType;
    }

    public function getReturnsAttribute(): ?Returns
    {
        return $this->spec()->returns;
    }

    public function getContinuationResultAttribute(): ?ContinuationResult
    {
        return $this->spec()->continuationResult;
    }

    public function getOperationDescriptorAttribute(): ?OperationDescriptor
    {
        return $this->spec()->operationDescriptor;
    }

    public function getOperationTitle(): ?string
    {
        return $this->spec()->operationDescriptor?->title;
    }

    public function getOperationDescription(): ?string
    {
        return $this->spec()->operationDescriptor?->description;
    }

    public function getOperationNote(): ?string
    {
        return $this->spec()->operationDescriptor?->note;
    }

    public function hasDownload(): bool
    {
        return $this->spec()->hasDownload;
    }

    public function hasNoAuth(): bool
    {
        return $this->spec()->hasNoAuth;
    }

    public function hasSkipCredentialsEnrichment(): bool
    {
        return $this->spec()->skipCredentialsEnrichment;
    }

    public function getAuthScopeAttribute(): ?AuthScope
    {
        return $this->spec()->authScope;
    }

    public function getAuthScope(): ?string
    {
        return $this->spec()->authScope?->scope;
    }

    public function getCacheAttribute(): ?Cache
    {
        return $this->spec()->cache;
    }

    public function getRetryAttribute(): ?Retry
    {
        return $this->spec()->retry;
    }

    public function getTimeoutAttribute(): ?Timeout
    {
        return $this->spec()->timeout;
    }

    public function hasRawResponse(): bool
    {
        return $this->spec()->hasRawResponse;
    }

    public function getIdempotentAttribute(): ?Idempotent
    {
        return $this->spec()->idempotent;
    }

    public function getExecutionAttribute(): ?Execution
    {
        return $this->spec()->execution;
    }

    public function getPaginationAttribute(): ?Pagination
    {
        return $this->spec()->pagination;
    }

    public function getCooldownAttribute(): ?Cooldown
    {
        return $this->spec()->cooldown;
    }

    public function getRateLimitAttribute(): ?RateLimit
    {
        return $this->spec()->rateLimit;
    }
}
