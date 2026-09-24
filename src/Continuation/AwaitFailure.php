<?php

declare(strict_types=1);

namespace ApiSutra\Continuation;

use ApiSutra\Result\ExecutionResult;

/** @internal Терминальное решение протокола; выдача выполняется после завершения await scope. */
final readonly class AwaitFailure
{
    public function __construct(public ExecutionResult $result)
    {
    }
}
