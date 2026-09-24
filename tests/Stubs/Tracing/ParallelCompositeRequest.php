<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Tracing;

use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Attributes\Behavior\Execution;
use ApiSutra\Enums\Execution\ExecutionMode;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Collections\RequestCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/composite')]
#[Execution(mode: ExecutionMode::Parallel)]
final class ParallelCompositeRequest extends AbstractRequest implements CompositeRequestInterface
{
    public function requests(): RequestCollection
    {
        return RequestCollection::make([
            new SimpleGetRequest('first'),
            new SimpleGetRequest('second'),
        ]);
    }

    public function aggregate(ResultCollection $results, PipelineContext $ctx): mixed
    {
        $names = [];
        foreach ($results->all() as $result) {
            $data = $result->data;
            if ($data instanceof SimpleResponseDto) {
                $names[] = $data->name;
            }
        }

        return $names;
    }
}
