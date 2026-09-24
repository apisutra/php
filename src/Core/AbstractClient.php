<?php

declare(strict_types=1);

namespace ApiSutra\Core;

use Override;
use ApiSutra\Contracts\Interfaces\Localization\LocalizableExceptionInterface;
use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Attributes\AttributeRegistry;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Collections\RequestCollection;
use ApiSutra\Config\BatchConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Continuation\ContinuationService;
use ApiSutra\Contracts\Interfaces\Attributes\AttributeMetadataCacheProviderInterface;
use ApiSutra\Contracts\Interfaces\Cache\CacheAwareInterface;
use ApiSutra\Contracts\Interfaces\Catalog\ProviderCatalogInterface;
use ApiSutra\Contracts\Interfaces\Catalog\ProviderCatalogRegistryInterface;
use ApiSutra\Contracts\Interfaces\Catalog\RequestBoundProviderCatalogInterface;
use ApiSutra\Contracts\Interfaces\Concurrency\ConcurrencyResolverInterface;
use ApiSutra\Contracts\Interfaces\Core\ContextualClientInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Contracts\Interfaces\Extensions\ExtensionInterface;
use ApiSutra\Contracts\Interfaces\Inventory\OperationInventoryInterface;
use ApiSutra\Contracts\Interfaces\Inventory\ResponseDtoCatalogProviderInterface;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Contracts\Interfaces\Execution\ResultPromiseInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Execution\Async\GuzzlePromiseBridge;
use ApiSutra\Localization\Message;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Promise\RejectionException;
use ApiSutra\Execution\BatchExecutor;
use ApiSutra\Execution\ClientExecutor;
use ApiSutra\Contracts\Interfaces\Execution\ClientExecutorInterface;
use ApiSutra\Execution\PoolExecutor;
use ApiSutra\Extensions\ExtensionRegistry;
use ApiSutra\Hooks\HookRegistry;
use ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog;
use ApiSutra\OperationInventory\OperationInventoryBuilder;
use ApiSutra\Pipeline\Pipeline;
use ApiSutra\RateLimiting\RateLimiter;
use ApiSutra\RateLimiting\Cooldown\CooldownBackendInterface;
use ApiSutra\RateLimiting\Cooldown\Backends\LocalCooldownBackend;
use ApiSutra\Request\RequestSpecResolver;
use ApiSutra\Resolver\ClassMapProvider;
use ApiSutra\Resolver\RequestScanner;
use ApiSutra\Response\ClientResponse;
use ApiSutra\Response\ClientResponseFactory;
use ApiSutra\Response\ClientResponseFactoryInterface;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResolvedResultFactory;
use ApiSutra\Result\ResolvedResultFactoryInterface;
use ApiSutra\Result\ResolvedResultInterface;
use ApiSutra\Result\ResultHandle;
use ApiSutra\Retry\RetryDelayCalculator;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Serializer;
use ApiSutra\Testing\MockClient;
use ApiSutra\Timing\SystemClock;
use ApiSutra\Timing\CooperativeSleeper;
use ApiSutra\Traits\DefaultRequestFailurePolicyTrait;
use ApiSutra\Traits\TestingClientTrait;
use ApiSutra\VO\Errors\ClientErrorMapperAwareInterface;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

abstract class AbstractClient implements ContextualClientInterface, AttributeMetadataCacheProviderInterface, ResponseDtoCatalogProviderInterface
{
    use TestingClientTrait;
    use DefaultRequestFailurePolicyTrait;

    private readonly HookRegistry $hooks;
    private readonly AttributeRegistry $attributes;
    private readonly ExtensionRegistry $extensions;
    private readonly CastRegistry $casts;
    private readonly Hydrator $hydrator;
    private readonly Serializer $serializer;
    private Pipeline $pipeline;
    private readonly RateLimiter $rateLimiter;
    private readonly CooldownBackendInterface $cooldownBackend;
    private readonly LoggerInterface $logger;
    private ?string $traceId = null;
    private readonly SleeperInterface $sleeper;
    private readonly AttributeMetadataCache $metadataCache;
    private ClientExecutor $executor;
    private readonly ResolvedResultFactoryInterface $resolvedResultFactory;
    private readonly ClientResponseFactoryInterface $clientResponseFactory;
    private ?ContinuationService $continuationService = null;
    private ?OperationInventoryInterface $operationInventory = null;
    private ?ResponseDtoCatalog $responseDtoCatalog = null;

    public function __construct(
        private readonly ClientConfig $config,
        private TransportInterface $transport,
        ?HookRegistry $hooks = null,
        ?AttributeRegistry $attributes = null,
        ?ExtensionRegistry $extensions = null,
        ?SleeperInterface $sleeper = null,
        private readonly ClockInterface $clock = new SystemClock(),
    ) {
        try {
            $this->logger = $config->logger ?? new NullLogger();
            $this->sleeper = $sleeper ?? new CooperativeSleeper();

            $this->metadataCache = $this->buildMetadataCache($config);
            $this->hooks = $hooks ?? new HookRegistry();
            $this->attributes = $attributes ?? new AttributeRegistry(cache: $this->metadataCache);
            $this->casts = new CastRegistry();
            $this->registerCasts($config);
            $this->extensions = $this->buildExtensionRegistry($extensions);
            $this->registerExtensions($config);
            $this->configureAuthCache($config);
            $this->applyGlobalMockTransport();
            $this->hydrator = new Hydrator($this->casts, $this->metadataCache, config: $config->hydration, localization: $config->localization);
            $this->serializer = Serializer::withDescriptions($this->casts, $this->metadataCache, $this->hydrator->descriptions(), $config->localization);
            $this->rateLimiter = new RateLimiter(clock: $this->clock, sleeper: $this->sleeper, backend: $config->rateLimitBackend);
            $this->cooldownBackend = $config->cooldownBackend ?? new LocalCooldownBackend($this->clock);
            $this->pipeline = $this->buildPipeline();
            $this->executor = new ClientExecutor($this->config, $this->pipeline, $this->clock, $this->traceId);

            $this->resolvedResultFactory = $config->resolvedResultFactory
                ?? new ResolvedResultFactory(
                    $config->errorMapper,
                    $config->errorContextFactory,
                    $config->continuationTokenExtractor,
                );
            $this->clientResponseFactory = $this->resolveResponseFactory($config);
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->config->localization);
        }
    }

    #[Override]
    public function execution(): ClientExecutorInterface
    {
        return $this->executor;
    }

    #[Override]
    public function send(RequestInterface $request): ResultHandle
    {
        return $this->deliver($this->execution()->execute($request), $request);
    }

    /** @return ResultPromiseInterface<ResultHandle> */
    #[Override]
    public function sendAsync(RequestInterface $request): ResultPromiseInterface
    {
        return $this->deliverAsync($request);
    }

    #[Override]
    public function sendInContext(RequestInterface $request, PipelineContext $parent, RequestRole $role): ResultHandle
    {
        return $this->deliver($this->execution()->execute($request, $role, $parent), $request);
    }

    /** @return ResultPromiseInterface<ResultHandle> */
    #[Override]
    public function sendInContextAsync(RequestInterface $request, PipelineContext $parent, RequestRole $role): ResultPromiseInterface
    {
        return $this->deliverAsync($request, $parent, $role);
    }

    private function deliver(ExecutionResult $result, RequestInterface $request): ResultHandle
    {
        if ($this->config->throwOnErrors) {
            $result->throw();
        }
        return new ResultHandle($result, $this->resolvedResultFactory, $this, $request);
    }

    /** @return ResultPromiseInterface<ResultHandle> */
    private function deliverAsync(RequestInterface $request, ?PipelineContext $parent = null, RequestRole $role = RequestRole::Root): ResultPromiseInterface
    {
        try {
            $promise = $this->execution()->executeAsync($request, $role, $parent);
        } catch (Throwable $exception) {
            $promise = new RejectedPromise($exception);
        }

        return (new GuzzlePromiseBridge())->wrap($promise)
            ->then(function (mixed $result) use ($request): ResultHandle {
                if (!$result instanceof ExecutionResult) {
                    throw new ConfigurationException(new Message('result.invalid_promise_result_type'));
                }
                return $this->deliver($result, $request);
            })
            ->otherwise(function (mixed $reason): never {
                $exception = $reason instanceof Throwable ? $reason : new RejectionException($reason);
                throw $exception instanceof LocalizableExceptionInterface
                    ? $exception->localized($this->config->localization)
                    : $exception;
            });
    }

    #[Override]
    public function response(ResolvedResultInterface $result): ClientResponse
    {
        return $this->clientResponseFactory->make($result);
    }

    public function batch(RequestCollection|array $requests, ?BatchConfig $config = null): BatchExecutor
    {
        if ($config !== null) {
            return BatchExecutor::fromConfig($this, $requests, $config);
        }

        return new BatchExecutor(client: $this, requests: $requests);
    }

    public function pool(
        iterable $requests,
        int|callable|ConcurrencyResolverInterface|null $concurrency = null,
    ): PoolExecutor {
        return new PoolExecutor($this, $requests, $concurrency, $this->config->pool);
    }

    #[Override]
    public function getAttributeMetadataCache(): AttributeMetadataCache
    {
        return $this->metadataCache;
    }

    private function resolveResponseFactory(ClientConfig $config): ClientResponseFactoryInterface
    {
        $factory = $config->responseFactory;
        if ($factory === null) {
            return new ClientResponseFactory($config->errorMapper);
        }

        if ($config->errorMapper !== null && $factory instanceof ClientErrorMapperAwareInterface) {
            return $factory->withErrorMapper($config->errorMapper);
        }

        return $factory;
    }

    public function clearCacheForRequest(RequestInterface $request): void
    {
        try {
            $this->pipeline->clearCache($request);
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->config->localization);
        }
    }

    public function registerExtension(ExtensionInterface $extension): static
    {
        try {
            $this->extensions->register($extension);
            return $this;
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->config->localization);
        }
    }

    public function getExtension(string $name): ?ExtensionInterface
    {
        return $this->extensions->get($name);
    }

    public function hooks(): HookRegistry
    {
        return $this->hooks;
    }

    #[Override]
    public function getConfig(): ClientConfig
    {
        return $this->config;
    }

    public function setTraceId(string $traceId): void
    {
        $this->traceId = $traceId;
        $this->pipeline->setTraceId($traceId);
        $this->executor->setTraceId($traceId);
    }

    public function clearTraceId(): void
    {
        $this->traceId = null;
        $this->pipeline->setTraceId(null);
        $this->executor->setTraceId(null);
    }

    public function clearCache(): void
    {
        $this->pipeline->clearCacheScope();
    }

    public function continuation(): ContinuationService
    {
        if ($this->continuationService === null) {
            $this->continuationService = new ContinuationService($this, $this->hydrator);
        }

        return $this->continuationService;
    }

    public function operationInventory(): OperationInventoryInterface
    {
        if ($this->operationInventory === null) {
            $builder = new OperationInventoryBuilder(
                requestScanner: new RequestScanner(new ClassMapProvider()),
                requestSpecResolver: new RequestSpecResolver($this->metadataCache),
            );
            $this->operationInventory = $builder->buildForClient($this);
        }

        return $this->operationInventory;
    }

    #[Override]
    public function responseDtoCatalog(): ResponseDtoCatalog
    {
        if ($this->responseDtoCatalog === null) {
            $this->responseDtoCatalog = new ResponseDtoCatalog($this->operationInventory());
        }

        return $this->responseDtoCatalog;
    }

    public function providerCatalogs(): ?ProviderCatalogRegistryInterface
    {
        return $this->config->providerCatalogRegistry;
    }

    public function providerCatalog(string $key): ?ProviderCatalogInterface
    {
        return $this->config->providerCatalogRegistry?->get($key);
    }

    /**
     * @return array<string, RequestBoundProviderCatalogInterface>
     */
    public function providerCatalogsForRequest(string $requestClass): array
    {
        return $this->config->providerCatalogRegistry?->forRequest($requestClass) ?? [];
    }

    public function hasRequestFailedInternal(ProviderResponse $response): bool
    {
        return $this->hasRequestFailed($response);
    }

    public function shouldRetryInternal(ProviderResponse $response, int $attempt): bool
    {
        return $this->shouldRetry($response, $attempt);
    }

    private function rebuildPipeline(): void
    {
        $this->pipeline = $this->buildPipeline();
        $this->executor = new ClientExecutor($this->config, $this->pipeline, $this->clock, $this->traceId);
    }

    public function getRequestExceptionInternal(ProviderResponse $response): ?Throwable
    {
        return $this->getRequestException($response);
    }

    private function buildPipeline(): Pipeline
    {
        return new Pipeline(
            config: $this->config,
            transport: $this->transport,
            serializer: $this->serializer,
            hydrator: $this->hydrator,
            hooks: $this->hooks,
            attributes: $this->attributes,
            extensions: $this->extensions,
            rateLimiter: $this->rateLimiter,
            retryDelayPolicy: new RetryDelayCalculator(),
            sleeper: $this->sleeper,
            logger: $this->logger,
            client: $this,
            clock: $this->clock,
            cooldownBackend: $this->cooldownBackend,
        );
    }

    private function buildMetadataCache(ClientConfig $config): AttributeMetadataCache
    {
        $cacheEnabled = $config->environment !== Environment::Local
            && $config->environment !== Environment::Testing;

        return new AttributeMetadataCache($cacheEnabled);
    }

    private function buildExtensionRegistry(?ExtensionRegistry $extensions): ExtensionRegistry
    {
        return $extensions ?? new ExtensionRegistry(
            casts: $this->casts,
            hooks: $this->hooks,
            attributes: $this->attributes,
        );
    }

    private function registerCasts(ClientConfig $config): void
    {
        foreach ($config->casts as $type => $cast) {
            $this->casts->register($type, $cast);
        }
    }

    private function registerExtensions(ClientConfig $config): void
    {
        foreach ($config->extensions as $extension) {
            if ($extension instanceof ExtensionInterface) {
                $this->extensions->register($extension);
            }
        }
    }

    private function configureAuthCache(ClientConfig $config): void
    {
        if ($config->auth instanceof CacheAwareInterface) {
            $cache = $config->cacheConfig?->store;
            if ($cache !== null) {
                $config->auth->setCache($cache);
            }
        }
    }

    private function applyGlobalMockTransport(): void
    {
        if (MockClient::hasGlobal()) {
            $this->transport = MockClient::global();
        }
    }
}
