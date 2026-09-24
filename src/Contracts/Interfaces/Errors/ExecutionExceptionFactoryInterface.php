<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Errors;

use ApiSutra\Result\ExecutionResult;
use Throwable;

/** Выбор исключения SDK провайдера после завершения выполнения. */
interface ExecutionExceptionFactoryInterface
{
    /** null сохраняет штатное исключение; message уже учитывает настройку Returns. */
    public function make(ExecutionResult $result, string $message): ?Throwable;
}
