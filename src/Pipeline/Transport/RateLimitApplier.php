<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Transport;

use ApiSutra\Localization\Message;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\RateLimiting\RateLimiter;
use ApiSutra\RateLimiting\RateLimitQuota;
use ApiSutra\Request\RequestOptions;
use ApiSutra\VO\Pipeline\PipelineContext;

final readonly class RateLimitApplier
{
    public function __construct(
        private ClientConfig $config,
        private RateLimiter $rateLimiter,
    ) {
    }

    public function apply(RequestInterface $request, PipelineContext $context): void
    {
        $options = $context->options;
        if ($request instanceof AbstractRequest && $this->disabled($request, $options)) {
            return;
        }
        $attribute = $request instanceof AbstractRequest ? $request->getRateLimitAttribute() : null;
        $includeClient = $attribute->includeClientQuota ?? $this->config->includeClientQuota;
        $rules = [];
        if ($includeClient && $this->config->rateLimit !== null) {
            $rules['client'] = $this->config->rateLimit;
        }
        $own = $request instanceof AbstractRequest ? $this->ownLimit($request, $options) : null;
        if ($own !== null) {
            $rules['operation'] = $own;
        }
        if ($rules === []) {
            return;
        }

        // Наследование store относится только к одиночному старому storage-пути.
        if (count($rules) === 1 && $this->config->rateLimitBackend === null) {
            $rule = reset($rules);
            $store = $rule->store ?? $this->config->rateLimit?->store;
            if ($store !== null) {
                $legacy = new RateLimitConfig($rule->limit, $rule->period, $rule->behavior, $store, $rule->key);
                $key = hash('sha256', 'apisutra.rate-limit.v2:' . ($rule->key ?? $context->config->baseUrl));
                $this->rateLimiter->acquire($legacy, $key, $context->budget);
                $context->budget?->check('rate_limit');
                return;
            }
        }

        $quotas = [];
        $behaviors = [];
        foreach ($rules as $kind => $rule) {
            if ($rule->store !== null) {
                throw new ConfigurationException(
                    new Message('pipeline.rate_limit_store_is_incompatible_with_shared_quotas_or'),
                );
            }
            $rawKey = $rule->key ?? ($kind === 'client' ? 'client' : $request::class);
            $key = hash('sha256', 'apisutra.rate-limit.v3:' . $kind . ':' . $rawKey);
            $quotas[] = new RateLimitQuota($key, $rule->limit, $rule->period * 1000);
            $behaviors[$key] = $rule->behavior;
        }
        $this->rateLimiter->acquireAll($quotas, $behaviors, $context->budget);
        $context->budget?->check('rate_limit');
    }

    private function disabled(AbstractRequest $request, ?RequestOptions $options): bool
    {
        if ($options?->getRateLimitDisabledOverride() === true) {
            return true;
        }
        if ($options?->getRateLimitOverride() !== null) {
            return false;
        }
        return $request->getRateLimitDisabledOverride() === true;
    }

    private function ownLimit(AbstractRequest $request, ?RequestOptions $options): ?RateLimitConfig
    {
        $override = $options?->getRateLimitOverride() ?? $request->getRateLimitOverride();
        if ($override !== null) {
            return $override;
        }
        $attribute = $request->getRateLimitAttribute();
        if ($attribute === null) {
            return null;
        }
        if ($attribute->limit === null && $attribute->period === null) {
            if ($attribute->key !== null || $attribute->behavior !== RateLimitBehavior::Wait) {
                throw new ConfigurationException(new Message('pipeline.custom_quota_key_and_behavior_require_both_limit_and'));
            }
            return null;
        }
        if ($attribute->limit === null || $attribute->period === null) {
            throw new ConfigurationException(new Message('pipeline.ratelimit_attribute_requires_both_limit_period_or_neither'));
        }
        return new RateLimitConfig(
            limit: $attribute->limit,
            period: $attribute->period,
            behavior: $attribute->behavior,
            key: $attribute->key,
        );
    }
}
