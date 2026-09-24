<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Continuation;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\ContinuationResult;
use ApiSutra\Collections\RequestCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/continuation/composite')]
#[ContinuationResult(CountingFinalDto::class)]
final class CompositeFinalRequest extends AbstractRequest implements CompositeRequestInterface
{
    public function requests(): RequestCollection
    {
        return RequestCollection::make([new UndeclaredRequest()]);
    }

    public function aggregate(ResultCollection $results, PipelineContext $ctx): mixed
    {
        return ['phase' => 'ready', 'data' => ['value' => $results->all()[0]->data['value']]];
    }
}
