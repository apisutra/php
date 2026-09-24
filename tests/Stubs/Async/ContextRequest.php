<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Async;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Request\RequestPaginationHelper;
use ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/ok')]
final class ContextRequest extends AbstractRequest
{
    public function protectedContext(): ?PipelineContext
    {
        return $this->context;
    }

    public function helper(): RequestPaginationHelper
    {
        return $this->paginationHelper();
    }
}
