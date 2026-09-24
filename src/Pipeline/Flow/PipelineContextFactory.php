<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Flow;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Enums\Pipeline\PipelineStage;
use ApiSutra\Pipeline\Attributes\StageProcessor;
use ApiSutra\Pipeline\Preparation\RequestPreparer;
use ApiSutra\Request\PaginationOptions;
use ApiSutra\Request\RequestOptions;
use ApiSutra\VO\Pipeline\PipelineContext;
use ApiSutra\Diagnostics\ExecutionTrace;

/**
 * Фабрика контекста пайплайна и стартовой телеметрии запроса.
 *
 * Инварианты:
 * - role может быть переопределён runtime options (и/или request-level override);
 * - traceId всегда нормализуется через RequestPreparer;
 * - связывание контекста с request выполняет владелец исполнения.
 *
 * @see docs/technical/pipeline.md
 * @see docs/guides/request-pipeline.md
 */
final readonly class PipelineContextFactory
{
    public function __construct(
        private ClientConfig $config,
        private RequestPreparer $requestPreparer,
        private StageProcessor $stageProcessor,
    ) {
    }

    public function create(
        RequestInterface $request,
        RequestRole $role,
        ?PipelineContext $parent,
        ?string $traceId,
        ?string $pipelineTraceId,
        ?RequestOptions $options = null,
        ?PaginationOptions $paginationOptions = null,
        ?ExecutionTrace $parentTrace = null,
    ): PipelineContext {
        $roleOverride = $options?->getRoleOverride();
        if ($roleOverride === null && $request instanceof AbstractRequest) {
            $roleOverride = $request->getRoleOverride();
        }
        if ($roleOverride !== null) {
            $role = $roleOverride;
        }

        $resolvedTraceId = $this->requestPreparer->resolveTraceId($request, $traceId, $pipelineTraceId, $options);
        $context = new PipelineContext(
            request: $request,
            config: $this->config,
            traceId: $resolvedTraceId,
            role: $role,
            parent: $parent,
            options: $options,
            paginationOptions: $paginationOptions,
            trace: ExecutionTrace::create($resolvedTraceId, $parentTrace ?? $parent?->trace),
        );

        return $context;
    }

    public function start(RequestInterface $request, PipelineContext $context, array &$audit): float
    {
        $startTime = microtime(true);
        $this->stageProcessor->process($request, $context, PipelineStage::Started);

        return $startTime;
    }
}
