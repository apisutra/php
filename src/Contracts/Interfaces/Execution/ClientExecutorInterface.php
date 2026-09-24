<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Execution;

use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Diagnostics\ExecutionTrace;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Pipeline\Diagnostics\ExecutionScope;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Promise\PromiseInterface;

/** Исполнение возвращает законченный результат независимо от способа выдачи ошибок. */
interface ClientExecutorInterface
{
    public function execute(
        RequestInterface $request,
        RequestRole $role = RequestRole::Root,
        ?PipelineContext $parent = null,
        ?ExecutionTrace $parentTrace = null,
        ?ClientExecutorInterface $dispatcher = null,
    ): ExecutionResult;

    /** @return PromiseInterface */
    public function executeAsync(
        RequestInterface $request,
        RequestRole $role = RequestRole::Root,
        ?PipelineContext $parent = null,
        ?ExecutionTrace $parentTrace = null,
        ?ClientExecutorInterface $dispatcher = null,
    ): PromiseInterface;

    /** Новый scope находится в Created; start/finish выполняет его владелец. */
    public function createScope(
        ?string $requestClass = null,
        ?ExecutionTrace $parent = null,
        ?string $traceId = null,
    ): ExecutionScope;
}
