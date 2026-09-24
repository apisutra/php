<?php

declare(strict_types=1);

namespace Example\ResultErrors;

use ApiSutra\Contracts\Interfaces\Errors\ExecutionExceptionFactoryInterface;
use ApiSutra\Result\ExecutionResult;
use Throwable;

final class ProviderExceptionFactory implements ExecutionExceptionFactoryInterface
{
    public function make(ExecutionResult $result, string $message): ?Throwable
    {
        if (($result->errors->first()?->context['reason'] ?? null) === 'response_type_mismatch') {
            return new ProviderException($message, previous: $result->exception);
        }

        // Остальные ошибки обслуживает штатная политика ApiSutra.
        return null;
    }
}
