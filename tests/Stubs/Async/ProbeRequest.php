<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Async;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\VO\Pipeline\PipelineContext;
use Closure;

#[Get('/probe')]
final class ProbeRequest extends AbstractRequest
{
    public function __construct(
        #[Query] public int $ms = 0,
        #[Query] public int $status = 200,
        #[Query] public int $observe = 1,
        private readonly ?Closure $before = null,
        private readonly ?Closure $after = null,
    ) {
    }

    protected function beforeSend(PipelineContext $context): void
    {
        ($this->before ?? static fn () => null)($context, $this->context);
    }

    protected function afterResponse(PipelineContext $context): void
    {
        ($this->after ?? static fn () => null)($context, $this->context);
    }
}
