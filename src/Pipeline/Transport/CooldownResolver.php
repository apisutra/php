<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Transport;

use ApiSutra\Auth\CacheCredentialIdentity;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\CooldownConfig;
use ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Http\Origin;
use ApiSutra\Localization\Message;
use ApiSutra\Pipeline\Auth\AuthHandler;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\VO\Pipeline\PipelineContext;
use Psr\Log\LogLevel;
use Throwable;

/** @internal Не зависит от CacheConfig и не читает тело запроса. */
final readonly class CooldownResolver
{
    public function __construct(private ClientConfig $config, private AuthHandler $auth, private AuditLogger $logger)
    {
    }

    public function resolve(PipelineContext $context): ?ResolvedCooldown
    {
        $request = $context->request;
        $config = $this->resolveConfig($context);
        if (!$config->enabled) {
            return null;
        }
        $prepared = $context->preparedRequest;
        $auth = $this->auth->resolveForRequest($request, $context);
        $identity = null;
        if ($auth instanceof CacheIdentityProviderInterface) {
            try {
                $identity = $auth->getCacheIdentity($prepared);
            } catch (Throwable $exception) {
                throw new ConfigurationException(new Message('rate_limit.cooldown_identity_failed'), previous: $exception);
            }
        }
        if ($auth !== null && ($identity === null || trim($identity) === '') && $config->identity === null) {
            $this->logger->log(LogLevel::DEBUG, new Message('rate_limit.cooldown_identity_unavailable'), [
                ...$context->trace->logContext(), 'reason' => 'cooldown_identity_unavailable',
            ]);
            return null;
        }
        return new ResolvedCooldown($config, hash('sha256', serialize([
            'apisutra-cooldown-v1', $prepared->destination->origin ?? Origin::fromUrl($prepared->url),
            $auth === null ? 'anonymous' : [$auth::class, $identity], $config->identity,
            $config->group ?? $request::class, CacheCredentialIdentity::credentialFields($prepared),
        ])));
    }

    private function resolveConfig(PipelineContext $context): CooldownConfig
    {
        $request = $context->request;
        $override = $context->options?->getCooldownOverride()
            ?? ($request instanceof AbstractRequest ? $request->getCooldownOverride() : null);
        if ($override !== null) {
            return $override;
        }
        $attribute = $request instanceof AbstractRequest ? $request->getCooldownAttribute() : null;
        return $attribute?->apply($this->config->cooldown) ?? $this->config->cooldown;
    }
}
