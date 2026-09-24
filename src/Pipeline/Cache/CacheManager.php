<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Cache;

use ApiSutra\Localization\Message;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Auth\CacheCredentialIdentity;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Cache\CacheMode;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Pipeline\Auth\AuthHandler;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Pipeline\Preparation\PreparedRequestFactory;
use ApiSutra\Pipeline\Preparation\RequestPreparer;
use ApiSutra\Request\PaginationOptions;
use ApiSutra\Request\RequestOptions;
use ApiSutra\VO\Cache\CacheOverride;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;
use Psr\Log\LogLevel;
use Psr\SimpleCache\CacheInterface;

/** Кеш ответов с автоматической identity и изолированным инвалидированием. */
final readonly class CacheManager
{
    private CacheIdentityResolver $identities;

    public function __construct(
        private ClientConfig $config,
        private PreparedRequestFactory $preparedRequestFactory,
        private RequestPreparer $requestPreparer,
        private AuthHandler $authHandler,
        string $provider,
    ) {
        $this->identities = new CacheIdentityResolver($config, $provider);
    }

    public function clearScope(): void
    {
        $cache = $this->config->cacheConfig ?? new CacheConfig();
        $store = $cache->store;
        if ($store === null) {
            return;
        }

        $scopes = [];
        foreach ([null, $this->config->auth, ...array_values($this->config->authScopes)] as $auth) {
            $scope = $this->identities->scope($auth, $cache, $cache->prefix);
            if ($scope !== null) {
                $scopes[$scope] = true;
            }
        }
        foreach (array_keys($scopes) as $scope) {
            (new CacheGenerations($store))->invalidate(CacheGenerations::key('scope', $scope));
        }
    }

    public function clearCache(
        RequestInterface $request,
        ?string $traceId,
        ?RequestOptions $options = null,
        ?PaginationOptions $paginationOptions = null,
    ): void {
        $state = $this->resolveCacheState($request, $options);
        if ($state === null) {
            return;
        }
        [$cache, $override, $store] = $state;
        $scope = $this->scope($request, $cache, $options);
        if ($scope === null || !$this->isAllowed($request, $cache, $override)) {
            return;
        }

        $prepared = $this->prepareForCache($request, $traceId, $options, $paginationOptions);
        if ($this->isFileUpload($prepared)) {
            return;
        }
        $group = $this->groupIdentity($request, $prepared);
        if ($group === null) {
            return;
        }
        (new CacheGenerations($store))->invalidate(
            CacheGenerations::key('group', $scope, $group),
            max(60, $override->ttl ?? $cache->ttl),
        );
    }

    /** Снимок поколения снимается до auth и hooks, способных выполнять I/O. */
    public function prepareExecution(RequestInterface $request, PipelineContext $context): void
    {
        $context->cacheExecution = null;
        $fileOperation = $this->isDownloadRequest($request)
            || ($context->preparedRequest !== null && $this->isFileUpload($context->preparedRequest));
        if ($context->destination?->preserveUrl || $fileOperation) {
            $mode = $context->options?->getCacheOverride()->mode;
            $attribute = $request instanceof AbstractRequest ? $request->getCacheAttribute() : null;
            if (
                ($mode !== null && $mode !== CacheMode::Disabled)
                || ($mode === null && $attribute !== null && $attribute->mode !== CacheMode::Disabled)
            ) {
                throw new ConfigurationException($fileOperation ? new Message('pipeline.http_cache_is_not_supported_for_file_operations') : new Message('pipeline.http_cache_is_not_supported_for_absolute_urls'));
            }
            return;
        }
        $state = $this->resolveCacheState($request, $context->options);
        if ($state === null || $context->preparedRequest === null) {
            return;
        }
        [$cache, $override, $store] = $state;
        $scope = $this->scope($request, $cache, $context->options, $context);
        if ($scope === null || $this->identities->request($request) === null) {
            (new AuditLogger($this->config))->log(LogLevel::DEBUG, new Message('pipeline.cache_skipped_identity_is_undefined'), [
                ...$context->trace->logContext(),
                'cache_reason' => 'unknown_identity',
            ]);
            return;
        }
        if (!$this->isAllowed($request, $cache, $override) || $this->isFileUpload($context->preparedRequest)) {
            return;
        }

        $ttl = $override->ttl ?? $cache->ttl;
        $generations = new CacheGenerations($store);
        $scopeKey = CacheGenerations::key('scope', $scope);
        $groupKey = CacheGenerations::key('group', $scope, $this->groupIdentity($request, $context->preparedRequest));
        $mode = $this->resolveCacheMode($cache, $override->mode);
        $create = $this->isWriteEnabled($mode);
        $scopeGeneration = $generations->current($scopeKey, create: $create);
        $groupGeneration = $generations->current($groupKey, max(60, $ttl), $create);
        if ($scopeGeneration === null || $groupGeneration === null) {
            return;
        }
        $context->cacheExecution = new CacheExecutionState(
            $store,
            $scopeKey,
            $scopeGeneration,
            $groupKey,
            $groupGeneration,
            $mode,
            $ttl,
            $scope,
            $this->identities->request($request),
        );
    }

    public function checkCache(RequestInterface $request, PipelineContext $context): ?ProviderResponse
    {
        $state = $context->cacheExecution;
        $prepared = $context->preparedRequest;
        if ($state === null || $prepared === null) {
            return null;
        }
        if (!$this->hasSameAccess($request, $context, $state) || $this->isFileUpload($prepared)) {
            $context->cacheExecution = null;
            return null;
        }

        $identity = $this->requestIdentity($prepared);
        $state->requestIdentity = $identity;
        $customKey = $request instanceof AbstractRequest ? $request->getCacheAttribute()?->key : null;
        $guard = $customKey !== null ? $this->identities->customGuard(
            $prepared,
            $request,
            $this->authHandler->resolveForRequest($request, $context),
            $this->resolveCacheConfig($request),
        ) : '';
        if ($guard === null) {
            (new AuditLogger($this->config))->log(LogLevel::DEBUG, new Message('pipeline.cache_skipped_final_identity_is_undefined'), [
                ...$context->trace->logContext(),
                'cache_reason' => 'unknown_identity',
            ]);
            $context->cacheExecution = null;
            return null;
        }
        $state->key = hash('sha256', serialize([
            'apisutra-response-v3', $state->scopeKey, $state->scopeGeneration,
            $state->groupKey, $state->groupGeneration,
            $customKey !== null ? ['custom', $customKey, $guard] : ['http', $identity],
        ]));
        if (!$this->isReadEnabled($state->mode) || !$this->isCurrent($state)) {
            return null;
        }
        $cached = $state->store->get($state->key);
        if (!is_array($cached) || !isset($cached['status'], $cached['headers'], $cached['body'])) {
            return null;
        }

        $state->hit = true;
        return $this->buildCachedResponse($cached, $context);
    }

    public function storeCache(RequestInterface $request, PipelineContext $context): void
    {
        $state = $context->cacheExecution;
        $response = $context->response;
        if (
            $state === null || $state->hit || $state->key === null || $response === null
            || !$response->isSuccess() || !$this->isWriteEnabled($state->mode) || !$this->isCurrent($state)
            || !$this->hasSameAccess($request, $context, $state)
        ) {
            return;
        }
        // Auth retry мог сменить credentials; исходный ключ тогда больше не подходит.
        if ($state->requestIdentity !== $this->requestIdentity($response->request)) {
            (new AuditLogger($this->config))->log(LogLevel::DEBUG, new Message('pipeline.cache_write_skipped_request_changed'), [
                ...$context->trace->logContext(),
                'cache_reason' => 'request_changed',
            ]);
            return;
        }

        if (
            !$state->store->set($state->key, [
            'status' => $response->status,
            'headers' => $response->headers,
            'body' => $response->body,
            ], $state->ttl)
        ) {
            throw new ConfigurationException(new Message('pipeline.failed_to_cache_the_response'));
        }
    }

    private function isCurrent(CacheExecutionState $state): bool
    {
        return $state->store->get($state->scopeKey) === $state->scopeGeneration
            && $state->store->get($state->groupKey) === $state->groupGeneration;
    }

    private function hasSameAccess(RequestInterface $request, PipelineContext $context, CacheExecutionState $state): bool
    {
        $cache = $this->resolveCacheConfig($request);
        return $this->scope($request, $cache, $context->options, $context) === $state->scopeIdentity
            && $this->identities->request($request) === $state->tenantIdentity;
    }

    private function scope(RequestInterface $request, CacheConfig $cache, ?RequestOptions $options, ?PipelineContext $context = null): ?string
    {
        $options ??= $request instanceof AbstractRequest ? $request->getOptions() : null;
        $context ??= new PipelineContext($request, $this->config, '', options: $options);
        $auth = $this->authHandler->resolveForRequest($request, $context);
        return $this->identities->scope($auth, $cache, $options?->getCacheScopeOverride() ?? $cache->prefix);
    }

    private function isAllowed(RequestInterface $request, CacheConfig $cache, CacheOverride $override): bool
    {
        $mode = $this->resolveCacheMode($cache, $override->mode);
        if ($mode === CacheMode::Disabled || $this->isDownloadCacheBlocked($request, $override->mode)) {
            return false;
        }
        if (in_array($request->getMethod()->value, ['GET', 'HEAD'], true)) {
            return true;
        }

        return $override->mode !== null
            || ($request instanceof AbstractRequest && $request->getCacheAttribute() !== null);
    }

    private function groupIdentity(RequestInterface $request, PreparedRequest $prepared): ?string
    {
        $tenant = $this->identities->request($request);
        if ($tenant === null) {
            return null;
        }
        $customKey = $request instanceof AbstractRequest ? $request->getCacheAttribute()?->key : null;
        if ($customKey !== null) {
            // Пользователь явно объединяет варианты запроса внутри своего пространства.
            return hash('sha256', serialize(['custom', $tenant, $customKey]));
        }

        return hash('sha256', serialize([$tenant, $this->requestIdentity($prepared)]));
    }

    private function requestIdentity(PreparedRequest $prepared): string
    {
        $headers = [];
        foreach (CacheCredentialIdentity::headersForCache($prepared) as $name => $value) {
            $headers[strtolower($name)][] = $value;
        }
        ksort($headers);

        return hash('sha256', serialize([
            $prepared->method->value, $prepared->url, $headers, $prepared->body,
        ]));
    }

    private function resolveCacheConfig(RequestInterface $request): CacheConfig
    {
        $cacheConfig = $this->config->cacheConfig ?? new CacheConfig();

        if ($request instanceof AbstractRequest) {
            $attribute = $request->getCacheAttribute();
            if ($attribute !== null) {
                return $cacheConfig->with(
                    ttl: $attribute->ttl ?? $cacheConfig->ttl,
                    mode: $attribute->mode,
                );
            }
        }

        return $cacheConfig;
    }

    private function prepareForCache(
        RequestInterface $request,
        ?string $traceId,
        ?RequestOptions $options,
        ?PaginationOptions $paginationOptions,
    ): PreparedRequest {
        $resolvedTraceId = $this->requestPreparer->resolveTraceId($request, null, $traceId, $options);
        $context = new PipelineContext(
            request: $request,
            config: $this->config,
            traceId: $resolvedTraceId,
            role: RequestRole::Root,
            options: $options,
            paginationOptions: $paginationOptions,
        );

        return $this->preparedRequestFactory->create($request, $context);
    }

    private function resolveCacheOverride(RequestInterface $request, ?RequestOptions $options): CacheOverride
    {
        if ($options !== null) {
            return $options->getCacheOverride();
        }

        if (!$request instanceof AbstractRequest) {
            return CacheOverride::empty();
        }

        return $request->getCacheOverride();
    }

    /**
     * @return array{CacheConfig, CacheOverride, CacheInterface}|null
     */
    private function resolveCacheState(RequestInterface $request, ?RequestOptions $options): ?array
    {
        $store = $this->config->cacheConfig?->store;
        if ($store === null) {
            return null;
        }

        return [$this->resolveCacheConfig($request), $this->resolveCacheOverride($request, $options), $store];
    }

    private function resolveCacheMode(CacheConfig $cache, ?CacheMode $overrideMode): CacheMode
    {
        return $overrideMode ?? $cache->mode;
    }

    private function isReadEnabled(CacheMode $mode): bool
    {
        return match ($mode) {
            CacheMode::Enabled, CacheMode::ReadOnly => true,
            CacheMode::Disabled, CacheMode::WriteOnly => false,
        };
    }

    private function isWriteEnabled(CacheMode $mode): bool
    {
        return match ($mode) {
            CacheMode::Enabled, CacheMode::WriteOnly => true,
            CacheMode::Disabled, CacheMode::ReadOnly => false,
        };
    }

    /**
     * @param array<string, mixed> $cached
     */
    private function buildCachedResponse(array $cached, PipelineContext $context): ProviderResponse
    {
        return new ProviderResponse(
            status: $cached['status'] ?? 200,
            headers: $cached['headers'] ?? [],
            body: $cached['body'] ?? '',
            request: $context->preparedRequest,
            duration: 0,
        );
    }

    private function isFileUpload(PreparedRequest $request): bool
    {
        // Upload с файлами не кешируется, чтобы исключить коллизии ключей.
        if ($request->stream !== null) {
            return true;
        }

        $files = $request->meta['files'] ?? null;
        return is_array($files) && $files !== [];
    }

    private function isDownloadRequest(RequestInterface $request): bool
    {
        return $request instanceof AbstractRequest && $request->hasDownload();
    }

    private function isDownloadCacheBlocked(RequestInterface $request, ?CacheMode $overrideMode): bool
    {
        return $this->isDownloadRequest($request)
            && !$this->isDownloadCacheAllowed($request, $overrideMode);
    }

    private function isDownloadCacheAllowed(RequestInterface $request, ?CacheMode $overrideMode): bool
    {
        if ($overrideMode !== null) {
            return $overrideMode !== CacheMode::Disabled;
        }

        if (!$request instanceof AbstractRequest) {
            return false;
        }

        $attribute = $request->getCacheAttribute();
        return $attribute !== null && $attribute->mode !== CacheMode::Disabled;
    }
}
