<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Transport;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Request\RequestOptions;

final readonly class RetryConfigResolver
{
    public function __construct(
        private ClientConfig $config,
    ) {
    }

    public function resolve(RequestInterface $request, ?RequestOptions $options = null): ?RetryConfig
    {
        $retry = $this->config->retry;
        if (!$request instanceof AbstractRequest) {
            return $retry;
        }

        $attribute = $request->getRetryAttribute();
        if ($attribute !== null) {
            $base = $retry ?? new RetryConfig();
            $retry = new RetryConfig(
                attempts: $attribute->attempts,
                baseDelay: $attribute->baseDelay,
                maxDelay: $attribute->maxDelay,
                backoff: $attribute->backoff,
                jitter: $attribute->jitter,
                retryOn: $attribute->retryOn,
                retryExceptions: $base->retryExceptions,
                totalTimeoutMs: $retry?->totalTimeoutMs,
                safeMethods: $base->safeMethods,
            );
        }

        $override = $options?->getRetryOverride() ?? $request->getRetryOverride();
        $overrideEnabled = $override['enabled'] ?? null;
        if ($overrideEnabled === false || ($overrideEnabled === null && $attribute?->enabled === false)) {
            return null;
        }

        if (($override['attempts'] ?? null) !== null) {
            $attempts = (int) $override['attempts'];
            $retry = $retry === null
                ? new RetryConfig(attempts: $attempts)
                : new RetryConfig(
                    attempts: $attempts,
                    baseDelay: $retry->baseDelay,
                    maxDelay: $retry->maxDelay,
                    backoff: $retry->backoff,
                    jitter: $retry->jitter,
                    retryOn: $retry->retryOn,
                    retryExceptions: $retry->retryExceptions,
                    totalTimeoutMs: $retry->totalTimeoutMs,
                    safeMethods: $retry->safeMethods,
                );
        } elseif ($overrideEnabled === true && $retry === null) {
            $retry = new RetryConfig();
        }

        return $retry;
    }
}
