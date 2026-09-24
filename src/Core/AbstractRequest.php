<?php

declare(strict_types=1);

namespace ApiSutra\Core;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Attributes\AttributeMetadataCacheProviderInterface;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Core\ClientResolverInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestOptionsProviderInterface;
use ApiSutra\Contracts\Interfaces\Validation\ValidatableInterface;
use ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Request\RequestExecution;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Request\RequestOptionsChainTrait;
use ApiSutra\Request\RequestPaginationHelper;
use ApiSutra\Request\RequestSpec;
use ApiSutra\Request\RequestSpecResolver;
use ApiSutra\Result\ResolvedResultInterface;
use ApiSutra\Result\ResultHandle;
use ApiSutra\Traits\DefaultRequestFailurePolicyTrait;
use ApiSutra\Traits\RequestHooksBridgeTrait;
use ApiSutra\Traits\RequestOptionsAccessorsTrait;
use ApiSutra\Traits\RequestSpecAccessorsTrait;
use ApiSutra\Traits\ValidatesAttributes;
use ApiSutra\Support\ContainerProviderRegistry;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;
use ApiSutra\Execution\ExecutionContextStack;
use Closure;
use ApiSutra\Contracts\Interfaces\Execution\ResultPromiseInterface;
use ApiSutra\Execution\Async\GuzzlePromiseBridge;
use GuzzleHttp\Promise\RejectedPromise;
use Throwable;

abstract class AbstractRequest implements RequestInterface, ValidatableInterface, RequestOptionsProviderInterface
{
    use ValidatesAttributes;
    use DefaultRequestFailurePolicyTrait;
    use RequestOptionsChainTrait;
    use RequestSpecAccessorsTrait;
    use RequestOptionsAccessorsTrait;
    use RequestHooksBridgeTrait;

    private ?RequestSpecResolver $specResolver = null;
    private ?RequestSpec $spec = null;
    private ?RequestPaginationHelper $paginationHelper = null;
    private ?RequestOptions $options = null;

    private ?ExecutionContextStack $executionContexts = null;
    // PHPCS 4 ошибочно считает переменные внутри property hook отдельными свойствами.
    // phpcs:disable PSR2.Classes.PropertyDeclaration.Multiple, PSR2.Classes.PropertyDeclaration.ScopeMissing
    protected ?ClientInterface $client = null {
        get {
            return $this->executionContexts?->current()->client ?? $this->client;
        }
    }
    protected ?PipelineContext $context = null {
        get {
            return $this->executionContexts?->current() ?? $this->context;
        }
    }
    // phpcs:enable PSR2.Classes.PropertyDeclaration.Multiple, PSR2.Classes.PropertyDeclaration.ScopeMissing


    /**
     * Синхронная отправка
     */
    public function send(): ResultHandle
    {
        return $this->resolveClient()->send($this);
    }

    /** @return ResultPromiseInterface<ResultHandle> */
    public function sendAsync(): ResultPromiseInterface
    {
        try {
            return $this->resolveClient()->sendAsync($this);
        } catch (Throwable $exception) {
            return (new GuzzlePromiseBridge())->wrap(new RejectedPromise($exception));
        }
    }

    public function resolved(): ResolvedResultInterface
    {
        return $this->send()->resolved();
    }

    /** @return ResultPromiseInterface<ResolvedResultInterface> */
    public function resolvedAsync(): ResultPromiseInterface
    {
        return $this->sendAsync()->then(static fn (ResultHandle $handle): ResolvedResultInterface => $handle->resolved());
    }

    public function dataOrFail(): mixed
    {
        return $this->send()->dataOrFail();
    }

    public function setClient(ClientInterface $client): static
    {
        $this->assertClientOwnership($client);
        $this->client = $client;
        $this->resetPaginationHelper();
        $this->resetSpecCache();
        return $this;
    }

    public function setContext(PipelineContext $context): void
    {
        $this->context = $context;
        $this->resetPaginationHelper();
    }

    public function __clone()
    {
        $this->executionContexts = null;
        $this->resetSpecCache();
        $this->resetPaginationHelper();
    }

    public function getContext(): ?PipelineContext
    {
        return $this->context;
    }

    /** @internal Вложенный запуск восстанавливает родителя, завершённый оставляет снимок контекста.
     * @return Closure(): void
     */
    public function enterExecutionContext(PipelineContext $context): Closure
    {
        $this->executionContexts ??= new ExecutionContextStack();
        $leave = $this->executionContexts->enter($context);
        return function () use ($context, $leave): void {
            $leave();
            $this->setContext($context);
        };
    }

    public function hasClient(): bool
    {
        return $this->client !== null;
    }

    public function isRoot(): bool
    {
        return $this->context?->role === RequestRole::Root;
    }

    protected function resolveClient(): ClientInterface
    {
        if ($this->client === null) {
            $resolver = $this->resolveClientResolver();
            if ($resolver !== null) {
                $this->client = $resolver->resolve($this);
            }
        }

        if ($this->client === null) {
            throw new ConfigurationException(new Message('core.no_client_is_bound_to_the_request'));
        }

        return $this->client;
    }

    public function getClient(): ClientInterface
    {
        return $this->resolveClient();
    }

    protected function resolveEndpoint(): ?string
    {
        return null;
    }

    protected function resolveBaseUrl(): ?string
    {
        return null;
    }

    private function options(): RequestOptions
    {
        if ($this->options === null) {
            $this->options = RequestOptions::empty();
        }

        return $this->options;
    }

    private function resetSpecCache(): void
    {
        $this->specResolver = null;
        $this->spec = null;
    }

    private function resetPaginationHelper(): void
    {
        $this->paginationHelper = null;
    }

    private function assertClientOwnership(ClientInterface $client): void
    {
        $resolver = $this->resolveClientResolver();
        if ($resolver === null) {
            return;
        }

        $resolver->assertOwnership($client, $this);
    }

    private function resolveClientResolver(): ?ClientResolverInterface
    {
        $provider = $this->resolveContainerProvider();
        if (!$provider->bound(ClientResolverInterface::class)) {
            return null;
        }

        $resolver = $provider->make(ClientResolverInterface::class);
        return $resolver instanceof ClientResolverInterface ? $resolver : null;
    }

    private function resolveContainerProvider(): ContainerProviderInterface
    {
        $provider = $this->client?->getConfig()->containerProvider;
        return ContainerProviderRegistry::resolve($provider);
    }

    private function spec(): RequestSpec
    {
        if ($this->spec === null) {
            $this->spec = $this->specResolver()->resolve($this);
        }

        return $this->spec;
    }

    private function specResolver(): RequestSpecResolver
    {
        if ($this->specResolver === null) {
            $metadataCache = null;
            if ($this->client instanceof AttributeMetadataCacheProviderInterface) {
                $metadataCache = $this->client->getAttributeMetadataCache();
            }
            $this->specResolver = new RequestSpecResolver($metadataCache);
        }

        return $this->specResolver;
    }

    protected function cloneWith(callable $mutate): static
    {
        $clone = clone $this;
        $mutate($clone);
        return $clone;
    }

    protected function currentOptions(): RequestOptions
    {
        return $this->options();
    }

    protected function executionFromOptions(RequestOptions $options): RequestExecutionInterface
    {
        return new RequestExecution($this, $options);
    }


    public function clearCache(): void
    {
        $this->resolveClient()->clearCacheForRequest($this);
    }

    protected function paginationHelper(): RequestPaginationHelper
    {
        $context = $this->context;
        if ($context !== null) {
            return $context->requestPaginationHelper ??= new RequestPaginationHelper($context, $this->client);
        }
        if ($this->paginationHelper === null) {
            $this->paginationHelper = new RequestPaginationHelper($this->context, $this->client);
        }

        return $this->paginationHelper;
    }


    protected static function validationMessages(): array
    {
        return [];
    }

    public function hasRequestFailedInternal(ProviderResponse $response): bool
    {
        return $this->hasRequestFailed($response);
    }

    public function shouldRetryInternal(ProviderResponse $response, int $attempt): bool
    {
        return $this->shouldRetry($response, $attempt);
    }

    public function getRequestExceptionInternal(ProviderResponse $response): ?Throwable
    {
        return $this->getRequestException($response);
    }
}
