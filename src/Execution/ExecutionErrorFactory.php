<?php

declare(strict_types=1);

namespace ApiSutra\Execution;

use ApiSutra\Diagnostics\SensitiveFields;
use ApiSutra\Diagnostics\RedactionPolicy;
use ApiSutra\Exceptions\Auth\OAuth2Exception;
use ApiSutra\Exceptions\ControlFlow\ExecutionCancelledException;
use ApiSutra\Exceptions\ControlFlow\ExecutorContractViolation;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Localization\ExceptionLocalization;
use ApiSutra\Localization\Message;
use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestOptionsProviderInterface;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\RateLimiting\RateLimitBackendException;
use ApiSutra\Exceptions\RateLimiting\CooldownBackendException;
use ApiSutra\Exceptions\Request\RateLimitException;
use ApiSutra\Exceptions\Request\CooldownException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Exceptions\Transport\TimeoutException;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Errors\RequestError;
use ApiSutra\VO\Errors\SystemErrorContextBuilder;
use ApiSutra\Exceptions\Auth\AuthDependencyException;
use ApiSutra\Exceptions\Auth\AuthRefreshFailedException;
use ApiSutra\Exceptions\Request\RequestException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Exceptions\Serialization\ResponseDecodingException;
use ApiSutra\Exceptions\Transport\ConnectionException;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Exceptions\Auth\AuthLockBackendException;
use ApiSutra\Exceptions\Auth\AuthRefreshLockTimeoutException;
use ApiSutra\Exceptions\Testing\RecordingException;
use ApiSutra\Exceptions\Files\FileTransferException;
use ApiSutra\Exceptions\Serialization\SerializationException;
use ApiSutra\Exceptions\Transport\InvalidRequestException;
use ApiSutra\Exceptions\Transport\TransportException;
use ApiSutra\Exceptions\Extension\ExtensionException;
use ApiSutra\Exceptions\Validation\ValidationException;
use Throwable;
use ApiSutra\Pipeline\Diagnostics\ExecutionEnvelope;

final readonly class ExecutionErrorFactory
{
    public function __construct(private LocalizationConfig $localization = new LocalizationConfig())
    {
    }

    public function buildExceptionResult(RequestInterface $request, Throwable $exception, ?ProviderResponse $response = null): ExecutionResult
    {
        if ($exception instanceof ExecutorContractViolation) {
            throw $exception;
        }
        if ($exception instanceof AuthDependencyException) {
            return $exception->dependencyResult;
        }
        $pipeline = $request instanceof ExecutionEnvelope ? $request->context : null;
        $localization = $pipeline?->config->localization ?? $this->localization;
        $response ??= $pipeline?->response;
        $source = $request instanceof RequestExecutionInterface ? $request->getRequest() : $request;
        $requestClass = $source::class;
        $traceId = $pipeline->traceId
            ?? ($request instanceof RequestOptionsProviderInterface ? $request->getOptions()->getTraceIdOverride() : null)
            ?? ($source instanceof AbstractRequest ? $source->getTraceIdOverride() : null);
        $localRateLimit = $exception instanceof RateLimitException && $exception->response === null;
        $response = match (true) {
            $exception instanceof ExecutionDeadlineException => $exception->response,
            $localRateLimit, $exception instanceof RateLimitBackendException, $exception instanceof CooldownBackendException => $exception->lastResponse,
            $exception instanceof RequestException, $exception instanceof RecordingException => $exception->response,
            default => $response,
        };
        $contextData = SystemErrorContextBuilder::build(
            traceId: $traceId,
            httpStatus: $response?->status,
            requestClass: $requestClass,
        );

        if ($pipeline?->retryRefusalReason !== null) {
            $contextData['retryRefusalReason'] = $pipeline->retryRefusalReason;
        }
        if ($exception instanceof OAuth2Exception) {
            $contextData['reason'] = $exception->reason->value;
        } elseif ($exception instanceof ExecutionCancelledException) {
            $contextData['reason'] = 'execution_cancelled';
        } elseif ($exception instanceof RecordingException) {
            $contextData['reason'] = 'recording_failed';
        } elseif ($exception instanceof HydrationException) {
            $contextData += $exception->context();
        } elseif ($exception instanceof AuthRefreshLockTimeoutException) {
            $contextData += ['reason' => 'auth_refresh_lock_timeout', 'stage' => 'auth_lock_wait'];
        } elseif ($exception instanceof AuthLockBackendException) {
            $contextData['reason'] = 'auth_lock_backend_error';
        } elseif ($exception instanceof AuthRefreshFailedException) {
            $contextData['reason'] = 'auth_refresh_failed';
        } elseif ($exception instanceof ExecutionDeadlineException) {
            $contextData['reason'] = 'execution_deadline_exceeded';
            $contextData['stage'] = $exception->stage;
            $contextData += array_filter(['bytesWritten' => $exception->bytesWritten, 'partial' => $exception->partial], static fn (mixed $value): bool => $value !== null);
        } elseif ($exception instanceof FileTransferException) {
            $contextData += ['stage' => $exception->stage, 'bytesWritten' => $exception->bytesWritten, 'partial' => $exception->partial];
        } elseif ($exception instanceof CooldownException) {
            $contextData += $exception->context();
        } elseif ($exception instanceof CooldownBackendException) {
            $contextData += $exception->context();
        } elseif ($localRateLimit) {
            $contextData += ['reason' => 'local_rate_limit_exceeded', 'stage' => 'rate_limit', 'retryAfter' => $exception->retryAfter];
        } elseif ($exception instanceof RateLimitBackendException) {
            $contextData += ['reason' => 'rate_limit_backend_error', 'stage' => 'rate_limit_store'];
        }

        return new ExecutionResult(
            redaction: SensitiveFields::policy($pipeline?->config->redaction ?? new RedactionPolicy(), $response->request ?? $pipeline?->preparedRequest),
            exceptionFactory: $pipeline?->config->resultExceptions?->exceptionFactory,
            data: null,
            traceId: $traceId,
            trace: $pipeline?->trace,
            status: ResultStatus::FAILED,
            errors: new ErrorCollection([
                new RequestError(
                    code: match (true) {
                        $localRateLimit => ErrorCode::RateLimited,
                        $exception instanceof RateLimitBackendException, $exception instanceof CooldownBackendException => ErrorCode::ExecutionError,
                        $exception instanceof TimeoutException => ErrorCode::Timeout,
                        $exception instanceof ConfigurationException => ErrorCode::ConfigurationError,
                        $exception instanceof RequestException && $response !== null => ErrorCode::fromHttpStatus($response->status),
                        $exception instanceof HydrationException => ErrorCode::HydrationError,
                        $exception instanceof ResponseDecodingException => ErrorCode::ResponseDecodingError,
                        $exception instanceof ConnectionException => ErrorCode::ConnectionFailed,
                        $exception instanceof FileTransferException => ErrorCode::FileTransferError,
                        $exception instanceof SerializationException => ErrorCode::SerializationError,
                        $exception instanceof InvalidRequestException => ErrorCode::InvalidRequest,
                        $exception instanceof TransportException => ErrorCode::TransportError,
                        $exception instanceof ExtensionException => ErrorCode::ExtensionError,
                        $exception instanceof ValidationException => ErrorCode::ValidationFailed,
                        default => $pipeline->failureCode ?? ErrorCode::ExecutionError,
                    },
                    message: ExceptionLocalization::message($exception),
                    localization: $localization,
                    context: $contextData,
                    requestClass: $requestClass,
                    response: $response,
                ),
            ]),
            requestClass: $requestClass,
            exception: ExceptionLocalization::apply($exception, $localization),
            localization: $localization,
            response: $response,
            validationErrors: $exception instanceof ValidationException ? $exception->errors : [],
        );
    }

    public function unsupportedItemMessage(string $scope, int $index, mixed $item): string
    {
        return $this->unsupportedItemException($scope, $index, $item)->getMessage();
    }

    public function invalidItemMessage(string $scope, int $index, mixed $item): string
    {
        return $this->invalidItemException($scope, $index, $item)->getMessage();
    }

    /** @internal Сохраняет дескриптор до границы выдачи исключения. */
    public function unsupportedItemException(string $scope, int $index, mixed $item): ConfigurationException
    {
        return new ConfigurationException(
            new Message('execution.unsupported_item_s_d_s', ['scope' => $scope, 'index' => $index, 'value2' => $this->describeItem($item)]),
            localization: $this->localization,
        );
    }

    /** @internal Сохраняет дескриптор до границы выдачи исключения. */
    public function invalidItemException(string $scope, int $index, mixed $item): ConfigurationException
    {
        return new ConfigurationException(
            new Message('execution.invalid_item_s_d_s_does_not_resolve_to', ['scope' => $scope, 'index' => $index, 'value2' => $this->describeItem($item)]),
            localization: $this->localization,
        );
    }

    private function describeItem(mixed $item): string
    {
        if (is_string($item)) {
            return 'string(' . $item . ')';
        }

        if (is_object($item)) {
            return $item::class;
        }

        return get_debug_type($item);
    }
}
