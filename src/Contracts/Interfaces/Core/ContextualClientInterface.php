<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Core;

use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Contracts\Interfaces\Execution\ResultPromiseInterface;
use ApiSutra\Result\ResultHandle;
use ApiSutra\VO\Pipeline\PipelineContext;

/** Вложенная отправка с наследованием контекста и срока родителя. */
interface ContextualClientInterface extends ClientInterface
{
    public function sendInContext(RequestInterface $request, PipelineContext $parent, RequestRole $role): ResultHandle;

    /** @return ResultPromiseInterface<ResultHandle> */
    public function sendInContextAsync(RequestInterface $request, PipelineContext $parent, RequestRole $role): ResultPromiseInterface;
}
