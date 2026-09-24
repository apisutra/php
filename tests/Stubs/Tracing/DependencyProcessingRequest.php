<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Tracing;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Collections\RequestCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Contracts\Interfaces\Core\DependsOnRequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Requests\DependencyTokenRequest;
use ApiSutra\VO\Pipeline\PipelineContext;
use RuntimeException;

#[Get('/dependency-processing')]
final class DependencyProcessingRequest extends AbstractRequest implements DependsOnRequestInterface
{
    public function dependencies(): RequestCollection
    {
        return RequestCollection::make([new DependencyTokenRequest()]);
    }

    public function processDependencies(ResultCollection $results, PipelineContext $ctx): void
    {
        throw new RuntimeException('Dependency processing failed');
    }
}
