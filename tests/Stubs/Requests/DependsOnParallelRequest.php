<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Behavior\Execution;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Collections\RequestCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Contracts\Interfaces\Core\DependsOnRequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Execution\ExecutionMode;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/depends-on/parallel')]
#[Execution(mode: ExecutionMode::Parallel, failStrategy: FailStrategy::FailAll)]
final class DependsOnParallelRequest extends AbstractRequest implements DependsOnRequestInterface
{
    public function __construct(
        #[Query]
        public ?string $token = null,
    ) {}

    public function dependencies(): RequestCollection
    {
        return RequestCollection::make([
            new DependencyTokenRequest(),
        ]);
    }

    public function processDependencies(ResultCollection $results, PipelineContext $ctx): void
    {
        $first = $results->all()[0] ?? null;
        $data = $first?->data;
        if (is_array($data) && isset($data['token'])) {
            $this->token = (string) $data['token'];
        }
    }
}
