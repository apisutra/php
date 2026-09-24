<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Collections\RequestCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Contracts\Interfaces\Core\DependsOnRequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/depends-on/main')]
final class DependsOnMainRequest extends AbstractRequest implements DependsOnRequestInterface
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
