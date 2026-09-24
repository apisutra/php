<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Execution;

/** Причина завершения обхода, независимая от статусов выполненных запросов. */
enum PoolTerminationReason: string
{
    case SourceExhausted = 'source_exhausted';
    case StopOnFailure = 'stop_on_failure';
    case SourceFailed = 'source_failed';
    case HandlerFailed = 'handler_failed';
    case FactoryFailed = 'factory_failed';
    case ExecutorFailed = 'executor_failed';
}
