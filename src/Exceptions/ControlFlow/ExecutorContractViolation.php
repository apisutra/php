<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\ControlFlow;

use LogicException;
use Throwable;

/** @internal Нарушение контракта чужого executor, а не перенос готового результата. */
final class ExecutorContractViolation extends LogicException
{
    public function __construct(public readonly Throwable $cause)
    {
        parent::__construct($cause->getMessage(), previous: $cause);
    }
}
