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
            $retry = $base->withOverrides(
                attempts: $attribute->attempts,
                baseDelay: $attribute->baseDelay,
                maxDelay: $attribute->maxDelay,
                backoff: $attribute->backoff,
                jitter: $attribute->jitter,
                retryOn: $attribute->retryOn,
            );
        }

        // Один источник runtime-опций: null поля не возвращают настройки другого источника.
        $source = $options ?? $request;
        $override = $source->getRetryOverride();
        $overrideEnabled = $override['enabled'] ?? null;
        if ($overrideEnabled === false || ($overrideEnabled === null && $attribute?->enabled === false)) {
            return null;
        }

        $attempts = isset($override['attempts']) ? (int) $override['attempts'] : null;
        if ($retry === null && ($attempts !== null || $overrideEnabled === true)) {
            $retry = new RetryConfig();
        }

        $delay = $source->getRetryDelayOverride();
        if ($retry !== null && ($attempts !== null || $delay !== null)) {
            $retry = $retry->withOverrides(
                attempts: $attempts,
                baseDelay: $delay?->baseDelay,
                maxDelay: $delay?->maxDelay,
                backoff: $delay?->backoff,
                jitter: $delay?->jitter,
            );
        }

        return $retry;
    }
}
