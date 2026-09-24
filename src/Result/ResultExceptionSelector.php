<?php

declare(strict_types=1);

namespace ApiSutra\Result;

use ApiSutra\Exceptions\Configuration\ExceptionFactoryException;
use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\Localization\Message;
use Throwable;

/** @internal Применяется при выдаче ошибки, вне классификации и повторных попыток. */
final class ResultExceptionSelector
{
    public static function select(ExecutionResult $result): Throwable
    {
        $message = $result->exception?->getMessage() ?? $result->errors->first()->message ?? new Message('result.request_execution_failed')->render($result->localization());
        if ($result->exceptionFactory !== null) {
            try {
                $exception = $result->exceptionFactory->make($result, $message);
            } catch (Throwable $failure) {
                throw new ExceptionFactoryException($result, $failure)->localized($result->localization());
            }
            if ($exception !== null) {
                return $exception;
            }
        }

        return $result->exception ?? new SdkException($message);
    }
}
