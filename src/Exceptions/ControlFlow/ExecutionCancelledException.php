<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\ControlFlow;

use ApiSutra\Localization\Message;

/** @internal Остановка локального продолжения; не свидетельствует об отмене на сервере. */
final class ExecutionCancelledException extends ControlFlowException
{
    public function __construct()
    {
        parent::__construct(new Message('execution.cancelled'));
    }
}
