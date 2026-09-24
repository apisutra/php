<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Diagnostics;

use ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestOptionsProviderInterface;
use ApiSutra\Diagnostics\ExecutionTrace;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Request\PaginationOptions;
use ApiSutra\Request\RequestOptions;
use ApiSutra\VO\Pipeline\PipelineContext;

/** @internal Явная передача служебного состояния одной отправки через существующий интерфейс. */
final class ExecutionEnvelope implements RequestExecutionInterface
{
    public ?PipelineContext $context = null;
    private readonly RequestInterface $request;
    private readonly RequestOptions $options;
    private readonly PaginationOptions $pagination;

    public function __construct(RequestInterface $request, public readonly ?ExecutionTrace $parentTrace = null, ?RequestOptions $options = null)
    {
        $this->options = $options ?? ($request instanceof RequestOptionsProviderInterface ? $request->getOptions() : RequestOptions::empty());
        $this->pagination = $request instanceof RequestExecutionInterface ? $request->getPaginationOptions() : PaginationOptions::empty();
        $this->request = $request instanceof RequestExecutionInterface ? $request->getRequest() : $request;
    }

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
        return $this->pagination;
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
}
