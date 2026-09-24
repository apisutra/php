<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Result;

use ApiSutra\Diagnostics\SensitiveFields;
use ApiSutra\Localization\Message;
use ApiSutra\Localization\ExceptionLocalization;
use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Pipeline\Error\ErrorPolicy;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Audit\DebugInfo;
use ApiSutra\VO\Errors\RequestError;
use ApiSutra\VO\Errors\SystemErrorContextBuilder;
use ApiSutra\VO\Pipeline\PipelineContext;

final readonly class ResultFactory
{
    public function __construct(
        private ErrorPolicy $errorPolicy,
    ) {
    }

    public function buildFailedResult(RequestInterface $request, PipelineContext $context, array $audit): ExecutionResult
    {
        $response = $context->response;
        $code = $response !== null ? ErrorCode::fromHttpStatus($response->status) : ErrorCode::ExecutionError;

        $contextData = SystemErrorContextBuilder::build(
            traceId: $context->traceId,
            httpStatus: $response?->status,
            requestClass: $request::class,
        );

        if ($context->retryRefusalReason !== null) {
            $contextData['retryRefusalReason'] = $context->retryRefusalReason;
        }

        $exception = $response ? $this->errorPolicy->getRequestExceptionInternal($request, $response, $context->budget?->clock) : null;
        $message = $exception !== null
            ? ExceptionLocalization::message($exception)
            : ($response?->errorMessage() ?? new Message('pipeline.request_failed.resultfactory'));
        $message = $context->destination?->redactReferences($message) ?? $message;
        $error = new RequestError(
            code: $code,
            message: $message,
            localization: $context->config->localization,
            response: $response,
            context: $contextData,
            requestClass: $request::class,
        );

        return new ExecutionResult(
            redaction: SensitiveFields::policy($context->config->redaction, $context->preparedRequest),
            exceptionFactory: $context->config->resultExceptions?->exceptionFactory,
            debug: $context->config->debug
                ? new DebugInfo($response->request ?? $context->preparedRequest, $response)
                : null,
            data: null,
            status: ResultStatus::FAILED,
            errors: new ErrorCollection([$error]),
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
