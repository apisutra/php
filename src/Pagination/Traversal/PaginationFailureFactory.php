<?php

declare(strict_types=1);

namespace ApiSutra\Pagination\Traversal;

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Diagnostics\SensitiveFields;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Execution\ExecutionErrorFactory;
use ApiSutra\Localization\Message;
use ApiSutra\Pipeline\Diagnostics\ExecutionEnvelope;
use ApiSutra\Pipeline\Diagnostics\ExecutionScope;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Errors\RequestError;
use ApiSutra\VO\Errors\SystemErrorContextBuilder;
use Throwable;

/** @internal Ошибки самого обхода: единые trace и политики без отправки или доставки исключения. */
final readonly class PaginationFailureFactory
{
    public function __construct(
        private AbstractRequest $request,
        private ClientConfig $config,
        private ExecutionScope $scope,
    ) {
    }

    public function exception(Throwable $exception): ExecutionResult
    {
        // Классификатор получает trace открытого обхода, а не изменяемый default запроса.
        $envelope = new ExecutionEnvelope($this->request, options: RequestOptions::empty()->withTraceId($this->scope->trace->traceId));
        $result = new ExecutionErrorFactory($this->config->localization)->buildExceptionResult($envelope, $exception);
        return $this->result($result->errors, $result);
    }

    public function guard(Message $message, int $page, string $reason): ExecutionResult
    {
        $error = new RequestError(
            code: ErrorCode::ExecutionError,
            message: new Message('pagination.pagination_stopped', ['reason' => $message]),
            localization: $this->config->localization,
            requestClass: $this->request::class,
            context: array_merge(SystemErrorContextBuilder::build(
                traceId: $this->scope->trace->traceId,
                httpStatus: null,
                requestClass: $this->request::class,
            ), ['page' => $page, 'reason' => $reason]),
        );
        return $this->result(new ErrorCollection([$error]));
    }

    private function result(ErrorCollection $errors, ?ExecutionResult $source = null): ExecutionResult
    {
        return new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: $errors,
            validationErrors: $source->validationErrors ?? [],
            exception: $source?->exception,
            response: $source?->response,
            requestClass: $this->request::class,
            redaction: SensitiveFields::policy($this->config->redaction, $source?->response?->request),
            exceptionFactory: $this->config->resultExceptions?->exceptionFactory,
            localization: $this->config->localization,
            trace: $this->scope->trace,
        );
    }
}
