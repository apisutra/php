<?php

declare(strict_types=1);

namespace ApiSutra\Request;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Pagination\PaginableInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Pagination\Paginator;
use ApiSutra\Result\ResolvedResultInterface;
use ApiSutra\Result\ResultHandle;
use ApiSutra\Contracts\Interfaces\Execution\ResultPromiseInterface;
use ApiSutra\Execution\Async\GuzzlePromiseBridge;
use GuzzleHttp\Promise\RejectedPromise;
use Throwable;

final readonly class RequestExecution implements RequestExecutionInterface
{
    use RequestOptionsChainTrait;

    public function __construct(
        private AbstractRequest $request,
        private RequestOptions $options,
        ?PaginationOptions $paginationOptions = null,
    ) {
        $this->paginationOptions = $paginationOptions ?? PaginationOptions::empty();
    }

    private PaginationOptions $paginationOptions;

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function getOptions(): RequestOptions
    {
        return $this->options;
    }

    public function getPaginationOptions(): PaginationOptions
    {
        return $this->paginationOptions;
    }

    public function send(): ResultHandle
    {
        return $this->request->getClient()->send($this);
    }

    /** @return ResultPromiseInterface<ResultHandle> */
    public function sendAsync(): ResultPromiseInterface
    {
        try {
            return $this->request->getClient()->sendAsync($this);
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

    public function clearCache(): void
    {
        $this->request->getClient()->clearCacheForRequest($this);
    }

    public function paginate(): Paginator
    {
        if (!$this->request instanceof PaginableInterface) {
            throw new ConfigurationException(new Message('request.request_does_not_support_pagination'));
        }

        return new Paginator($this->request, $this->options, $this->paginationOptions);
    }

    public function getMethod(): HttpMethod
    {
        return $this->request->getMethod();
    }

    public function getEndpoint(): string
    {
        return $this->request->getEndpoint();
    }

    public function getResponseType(): ?string
    {
        return $this->request->getResponseType();
    }

    public function withPage(int $page): self
    {
        if (!$this->request instanceof PaginableInterface) {
            throw new ConfigurationException(new Message('request.request_does_not_support_pagination'));
        }

        return new self(
            request: $this->request,
            options: $this->options,
            paginationOptions: $this->paginationOptions->withPage($page),
        );
    }

    public function withLimit(int $limit): self
    {
        if (!$this->request instanceof PaginableInterface) {
            throw new ConfigurationException(new Message('request.request_does_not_support_pagination'));
        }

        return new self(
            request: $this->request,
            options: $this->options,
            paginationOptions: $this->paginationOptions->withLimit($limit),
        );
    }

    public function withCursor(?string $cursor): self
    {
        if (!$this->request instanceof PaginableInterface) {
            throw new ConfigurationException(new Message('request.request_does_not_support_pagination'));
        }

        return new self(
            request: $this->request,
            options: $this->options,
            paginationOptions: $this->paginationOptions->withCursor($cursor),
        );
    }

    protected function currentOptions(): RequestOptions
    {
        return $this->options;
    }

    protected function executionFromOptions(RequestOptions $options): RequestExecutionInterface
    {
        return new self($this->request, $options, $this->paginationOptions);
    }
}
