<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Flow;

use ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use ApiSutra\Contracts\Interfaces\Core\DependsOnRequestInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Pipeline\Execution\CompositeFlow;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Pipeline\PipelineContext;

final readonly class PipelineCompositeHandler
{
    public function __construct(
        private CompositeFlow $compositeFlow,
    ) {
    }

    public function handle(
        RequestInterface $request,
        PipelineContext $context,
    ): ?ExecutionResult {
        return match (true) {
            $request instanceof CompositeRequestInterface => $this->compositeFlow->executeComposite($request, $context),
            $request instanceof DependsOnRequestInterface => $this->compositeFlow->executeDependsOn($request, $context),
            default => null,
        };
    }
}
