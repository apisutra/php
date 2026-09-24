<?php

declare(strict_types=1);

namespace ApiSutra\Traits;

use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Config\CooldownConfig;
use ApiSutra\Enums\Auth\AuthOverride;
use ApiSutra\Enums\Continuation\ContinuationMode;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Enums\Request\CredentialsMergeMode;
use ApiSutra\Request\RequestOptions;
use ApiSutra\VO\Cache\CacheOverride;

/**
 * Доступ к runtime‑override опциям запроса.
 */
trait RequestOptionsAccessorsTrait
{
    public function getOptions(): RequestOptions
    {
        return $this->options();
    }

    public function getBaseUrl(): ?string
    {
        return $this->options()->getBaseUrlOverride() ?? $this->resolveBaseUrl();
    }

    public function getCacheOverride(): CacheOverride
    {
        return $this->options()->getCacheOverride();
    }

    public function getRetryOverride(): array
    {
        return $this->options()->getRetryOverride();
    }

    public function getAuthOverride(): ?AuthOverride
    {
        return $this->options()->getAuthOverride();
    }

    public function getAuthScopeOverride(): ?string
    {
        return $this->options()->getAuthScopeOverride();
    }

    public function getDelayOverride(): ?int
    {
        return $this->options()->getDelayOverride();
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->options()->getIdempotencyKey();
    }

    public function getCooldownOverride(): ?CooldownConfig
    {
        return $this->options()->getCooldownOverride();
    }

    public function getRateLimitOverride(): ?RateLimitConfig
    {
        return $this->options()->getRateLimitOverride();
    }

    public function getRateLimitDisabledOverride(): ?bool
    {
        return $this->options()->getRateLimitDisabledOverride();
    }

    public function getTimeoutOverride(): ?int
    {
        return $this->options()->getTimeoutOverride();
    }

    public function getConnectTimeoutOverride(): ?int
    {
        return $this->options()->getConnectTimeoutOverride();
    }

    public function getTraceIdOverride(): ?string
    {
        return $this->options()->getTraceIdOverride();
    }

    public function getRoleOverride(): ?RequestRole
    {
        return $this->options()->getRoleOverride();
    }

    /**
     * @return array<string, string>
     */
    public function getHeadersOverride(): array
    {
        return $this->options()->getHeadersOverride();
    }

    public function getCredentialsEnrichmentEnabledOverride(): ?bool
    {
        return $this->options()->getCredentialsEnrichmentEnabledOverride();
    }

    public function getCredentialsMergeModeOverride(): ?CredentialsMergeMode
    {
        return $this->options()->getCredentialsMergeModeOverride();
    }

    public function getCredentialsScopeOverride(): ?string
    {
        return $this->options()->getCredentialsScopeOverride();
    }

    public function getContinuationModeOverride(): ?ContinuationMode
    {
        return $this->options()->getContinuationModeOverride();
    }
}
