<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Flow;

use ApiSutra\Diagnostics\SensitiveFields;
use ApiSutra\Exceptions\Auth\OAuth2Exception;
use ApiSutra\Exceptions\ControlFlow\ExecutorContractViolation;
use ApiSutra\Pipeline\Hydration\ResponseContractGuard;
use ApiSutra\Localization\ExceptionLocalization;
use ApiSutra\Localization\Message;
use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResultMeta;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Exceptions\Auth\AuthLockBackendException;
use ApiSutra\Exceptions\Auth\AuthDependencyException;
use ApiSutra\Exceptions\Auth\AuthRefreshFailedException;
use ApiSutra\Exceptions\Auth\AuthRefreshLockTimeoutException;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\ControlFlow\EarlyReturnException;
use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\Exceptions\Extension\ExtensionException;
use ApiSutra\Exceptions\Files\FileTransferException;
use ApiSutra\Exceptions\RateLimiting\RateLimitBackendException;
use ApiSutra\Exceptions\RateLimiting\CooldownBackendException;
use ApiSutra\Exceptions\Request\RateLimitException;
use ApiSutra\Exceptions\Request\CooldownException;
use ApiSutra\Exceptions\Request\RequestException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Exceptions\Serialization\ResponseDecodingException;
use ApiSutra\Exceptions\Serialization\SerializationException;
use ApiSutra\Exceptions\Transport\ConnectionException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Exceptions\Transport\InvalidRequestException;
use ApiSutra\Exceptions\Transport\TimeoutException;
use ApiSutra\Exceptions\Transport\TransportException;
use ApiSutra\Exceptions\Validation\ValidationException;
use ApiSutra\Pipeline\Hydration\ResponseHydrator;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Audit\DebugInfo;
use ApiSutra\VO\Errors\RequestError;
use ApiSutra\VO\Errors\SystemErrorContextBuilder;
use ApiSutra\VO\Errors\ValidationError;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;
use ApiSutra\Exceptions\Testing\RecordingException;
use Throwable;
use ApiSutra\Exceptions\Retry\RetrySafetyException;

/**
 * Строитель ExecutionResult для всех веток пайплайна.
 *
 * Инварианты:
 * - SUCCESS/PARTIAL/FAILED формируются в одном месте по единым правилам;
 * - системный error context всегда содержит traceId/httpStatus/requestClass;
 * - при throwOnErrors исключения пробрасываются, иначе упаковываются в ExecutionResult.
 *
 * @see docs/guides/errors.md
 * @see docs/technical/error-handling.md
 * @see docs/technical/pipeline.md
 */
final readonly class ExecutionResultBuilder
{
    public function __construct(
        private ClientConfig $config,
        private ResponseHydrator $responseHydrator,
    ) {
    }

    /**
     * @param array<int, ValidationError> $validationErrors
     */
    public function buildValidationFailure(
        RequestInterface $request,
        PipelineContext $context,
        array &$audit,
        float $startTime,
        array $validationErrors,
    ): ExecutionResult {
        $error = $this->createRequestError(
            code: ErrorCode::ValidationFailed,
            message: new Message('pipeline.request_validation_failed'),
            request: $request,
            context: $context,
        );

        $result = $this->createFailedResult(
            request: $request,
            context: $context,
            audit: $audit,
            errors: new ErrorCollection([$error]),
            exception: new ValidationException($validationErrors),
            validationErrors: $validationErrors,
        );

        return $result;
    }

    public function buildSuccessResult(
        RequestInterface $request,
        PipelineContext $context,
        array &$audit,
        float $startTime,
        PreparedRequest $prepared,
        mixed $data,
        ?ResultMeta $meta = null,
    ): ExecutionResult {
        $duration = $context->scope?->duration() ?? 0.0;
        $debug = $this->config->debug
            ? new DebugInfo($context->response->request ?? $context->preparedRequest ?? $prepared, $context->response, $duration)
            : null;

        return $this->createSuccessResult(
            request: $request,
            context: $context,
            audit: $audit,
            data: $data,
            debug: $debug,
            meta: $meta,
            response: $context->response,
        );
    }

    public function buildRequestContractViolation(
        RequestInterface $request,
        PipelineContext $context,
        array &$audit,
        float $startTime,
        RequestContractViolation $violation,
    ): ExecutionResult {
        $message = $violation->definition();
        $error = $this->createRequestError(
            code: ErrorCode::RequestContractViolation,
            message: $message,
            request: $request,
            context: $context,
            overrideContext: $violation->context($this->config->localization),
        );

        $result = $this->createFailedResult(
            request: $request,
            context: $context,
            audit: $audit,
            errors: new ErrorCollection([$error]),
            exception: new SdkException($message),
        );

        return $result;
    }

    public function buildEarlyReturnResult(
        RequestInterface $request,
        PipelineContext $context,
        array &$audit,
        float $startTime,
        PreparedRequest $prepared,
        EarlyReturnException $exception,
    ): ExecutionResult {
        $resultData = $this->responseHydrator->hydrateResponse($request, $context, $exception->data);
        ResponseContractGuard::check($request, $this->config, $resultData);
        $debug = $this->config->debug ? new DebugInfo($context->preparedRequest ?? $prepared, null, null) : null;

        return $this->createSuccessResult(
            request: $request,
            context: $context,
            audit: $audit,
            data: $resultData,
            debug: $debug,
            response: $context->response,
        );
    }

    public function buildExceptionResult(
        RequestInterface $request,
        PipelineContext $context,
        array &$audit,
        float $startTime,
        Throwable $exception,
    ): ExecutionResult {
        if ($exception instanceof ExecutorContractViolation) {
            throw $exception;
        }
        $exception = ExceptionLocalization::apply($exception, $this->config->localization);
        if ($exception instanceof AuthDependencyException) {
            $dependency = $exception->dependencyResult;

            return $this->createFailedResult(
                $request,
                $context,
                $audit,
                $dependency->errors,
                $dependency->exception ?? $exception,
                $dependency->validationErrors,
                $dependency->response,
            );
        }
        $code = $context->failureCode ?? match (true) {
            $exception instanceof FileTransferException => ErrorCode::FileTransferError,
            $exception instanceof SerializationException => ErrorCode::SerializationError,
            $exception instanceof ResponseDecodingException => ErrorCode::ResponseDecodingError,
            $exception instanceof HydrationException => ErrorCode::HydrationError,
            $exception instanceof TimeoutException => ErrorCode::Timeout,
            $exception instanceof ConnectionException => ErrorCode::ConnectionFailed,
            $exception instanceof InvalidRequestException => ErrorCode::InvalidRequest,
            $exception instanceof TransportException => ErrorCode::TransportError,
            $exception instanceof ExtensionException => ErrorCode::ExtensionError,
            default => ErrorCode::ExecutionError,
        };
        $response = $context->response;

        if ($exception instanceof RecordingException) {
            $response = $exception->response;
            $code = ErrorCode::ExecutionError;
        } elseif ($exception instanceof ExecutionDeadlineException) {
            $code = ErrorCode::Timeout;
            $response = $context->response ?? $context->lastResponse ?? $exception->response;
        } elseif ($exception instanceof RateLimitException && $exception->response === null) {
            $response = $context->response ?? $context->lastResponse ?? $exception->lastResponse;
            $code = ErrorCode::RateLimited;
        } elseif ($exception instanceof RateLimitBackendException || $exception instanceof CooldownBackendException) {
            $response = $context->response ?? $context->lastResponse ?? $exception->lastResponse;
            $code = ErrorCode::ExecutionError;
        } elseif ($exception instanceof RequestException) {
            $response = $exception->response;
            $code = $response === null ? ErrorCode::ExecutionError : ErrorCode::fromHttpStatus($response->status);
        } elseif ($exception instanceof ConfigurationException) {
            $code = ErrorCode::ConfigurationError;
        }

        $error = $this->createRequestError(
            code: $code,
            message: $context->destination?->preserveUrl ? new Message('pipeline.request_execution_failed_for_an_absolute_url') : ExceptionLocalization::message($exception),
            request: $request,
            context: $context,
            response: $response,
            overrideContext: match (true) {
                $exception instanceof OAuth2Exception => ['reason' => $exception->reason->value],
                $exception instanceof AuthRefreshFailedException => ['reason' => 'auth_refresh_failed'],
                $exception instanceof RecordingException => ['reason' => 'recording_failed'],
                $exception instanceof CooldownException, $exception instanceof CooldownBackendException => $exception->context(),
                $exception instanceof RateLimitException && $exception->response === null => [
                    'reason' => 'local_rate_limit_exceeded', 'stage' => 'rate_limit', 'retryAfter' => $exception->retryAfter,
                ],
                $exception instanceof RateLimitBackendException => [
                    'reason' => 'rate_limit_backend_error', 'stage' => 'rate_limit_store',
                ],
                $exception instanceof ExecutionDeadlineException => array_filter([
                    'reason' => 'execution_deadline_exceeded', 'stage' => $exception->stage,
                    'bytesWritten' => $exception->bytesWritten, 'partial' => $exception->partial,
                ], static fn (mixed $value): bool => $value !== null),
                $exception instanceof FileTransferException => [
                    'stage' => $exception->stage, 'bytesWritten' => $exception->bytesWritten, 'partial' => $exception->partial,
                ],
                $exception instanceof AuthRefreshLockTimeoutException => [
                    'reason' => 'auth_refresh_lock_timeout', 'stage' => 'auth_lock_wait',
                ],
                $exception instanceof AuthLockBackendException => ['reason' => 'auth_lock_backend_error'],
                $exception instanceof RetrySafetyException => ['reason' => 'retry_safety_check_failed'],
                $exception instanceof ResponseDecodingException && $exception->reason !== null => ['reason' => $exception->reason],
                $exception instanceof HydrationException => $exception->context(),
                default => [],
            },
        );

        $result = $this->createFailedResult(
            request: $request,
            context: $context,
            audit: $audit,
            errors: new ErrorCollection([$error]),
            exception: $exception,
            response: $response,
        );



        return $result;
    }

    private function createRequestError(
        ErrorCode $code,
        string|Message $message,
        RequestInterface $request,
        PipelineContext $context,
        ?ProviderResponse $response = null,
        array $overrideContext = [],
    ): RequestError {
        $systemContext = SystemErrorContextBuilder::build(
            traceId: $context->traceId,
            httpStatus: $response?->status,
            requestClass: $request::class,
        );

        $contextData = array_merge($systemContext, $overrideContext);
        $contextData['transmissionState'] = $context->transmissionState->value;
        if ($context->retryRefusalReason !== null) {
            $contextData['retryRefusalReason'] = $context->retryRefusalReason;
        }

        return new RequestError(
            code: $code,
            message: $message,
            localization: $this->config->localization,
            response: $response,
            context: $contextData,
            requestClass: $request::class,
        );
    }

    private function createSuccessResult(
        RequestInterface $request,
        PipelineContext $context,
        array $audit,
        mixed $data,
        ?DebugInfo $debug = null,
        ?ResultMeta $meta = null,
        ?ProviderResponse $response = null,
    ): ExecutionResult {
        return new ExecutionResult(
            redaction: SensitiveFields::policy($this->config->redaction, $context->preparedRequest),
            exceptionFactory: $this->config->resultExceptions?->exceptionFactory,
            data: $data,
            status: ResultStatus::SUCCESS,
            errors: new ErrorCollection([]),
            debug: $debug,
            traceId: $context->traceId,
            trace: $context->trace,
            nested: $context->nested,
            audit: $audit,
            meta: $meta,
            requestClass: $request::class,
            response: $response,
        );
    }

    /**
     * @param array<int, ValidationError> $validationErrors
     */
    private function createFailedResult(
        RequestInterface $request,
        PipelineContext $context,
        array $audit,
        ErrorCollection $errors,
        ?Throwable $exception = null,
        array $validationErrors = [],
        ?ProviderResponse $response = null,
    ): ExecutionResult {
        return new ExecutionResult(
            redaction: SensitiveFields::policy($this->config->redaction, $context->preparedRequest),
            exceptionFactory: $this->config->resultExceptions?->exceptionFactory,
            data: null,
            status: ResultStatus::FAILED,
            errors: $errors,
            validationErrors: $validationErrors,
            debug: $this->config->debug
                ? new DebugInfo($response->request ?? $context->preparedRequest, $response)
                : null,
            traceId: $context->traceId,
            trace: $context->trace,
            nested: $context->nested,
            audit: $audit,
            requestClass: $request::class,
            response: $response,
            exception: $exception,
        );
    }
}
