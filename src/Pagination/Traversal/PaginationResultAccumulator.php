<?php

declare(strict_types=1);

namespace ApiSutra\Pagination\Traversal;

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\PaginationConfig;
use ApiSutra\Pagination\PaginationItemsReader;
use ApiSutra\Diagnostics\SensitiveFields;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Pagination\PaginationItemsCollectionBuilder;
use ApiSutra\Pipeline\Diagnostics\ExecutionScope;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\PaginatedResult;
use ApiSutra\VO\Errors\RequestError;
use ApiSutra\VO\Metadata\PaginationMeta;

/** @internal Накопление страниц и линейная сборка; без HTTP и публичной доставки исключений. */
final class PaginationResultAccumulator
{
    /** @var array<int, ExecutionResult> */
    private array $pages = [];
    /** @var array<int, PaginationMeta> */
    private array $metadata = [];
    private ?ExecutionResult $failure = null;
    private bool $fatal = false;
    private bool $hasItems = false;

    public function __construct(
        private readonly ClientConfig $config,
        private readonly PaginationConfig $pagination,
        private readonly string $requestClass,
        private readonly ExecutionScope $scope,
    ) {
    }

    public function record(int $page, ExecutionResult $result): void
    {
        $this->pages[$page] = $result;
    }

    public function meta(int $page, PaginationMeta $meta): void
    {
        $this->metadata[$page] = $meta;
    }

    public function fail(ExecutionResult $failure, bool $fatal = false): void
    {
        $this->failure = $failure;
        $this->fatal = $this->fatal || $fatal;
    }

    /** @return list<ExecutionResult> */
    public function pages(): array
    {
        ksort($this->pages);
        return array_values($this->pages);
    }

    public function build(): PaginatedResult
    {
        $pages = $this->pages();
        ksort($this->metadata);
        $items = [];
        $errors = $this->failure?->errors->all() ?? [];
        $firstFailure = null;
        $builder = new PaginationItemsCollectionBuilder();
        $reader = new PaginationItemsReader();
        foreach ($this->pages as $page => $result) {
            foreach ($result->errors as $error) {
                $errors[] = new RequestError(
                    code: $error->code,
                    message: $error->message,
                    messageDefinition: $error->messageDefinition(),
                    requestClass: $error->requestClass ?? $this->requestClass,
                    response: $error->response ?? $result->response,
                    nested: $error->nested,
                    context: array_merge($error->context, ['page' => $page]),
                );
            }
            if ($result->isFailed()) {
                $firstFailure ??= $result;
                continue;
            }
            if (!$this->fatal) {
                $reader->append($items, $result->data);
            }
        }
        $this->hasItems = $items !== [];
        $status = match (true) {
            $this->fatal => ResultStatus::FAILED,
            $errors === [] => ResultStatus::SUCCESS,
            $this->hasItems => ResultStatus::PARTIAL,
            default => ResultStatus::FAILED,
        };
        return new PaginatedResult(
            data: $this->fatal ? null : $builder->build($items, $this->pagination),
            status: $status,
            errors: new ErrorCollection($errors),
            meta: $this->metadata === [] ? null : $this->metadata[array_key_last($this->metadata)],
            nested: $pages,
            requestClass: $this->requestClass,
            exception: $this->failure !== null ? $this->failure->exception : $firstFailure?->exception,
            response: $this->failure?->response,
            exceptionFactory: $this->config->resultExceptions?->exceptionFactory,
            localization: $this->config->localization,
            trace: $this->scope->trace,
            audit: $this->scope->audit,
        );
    }

    public function interrupted(PaginatedResult $result, ExecutionResult $failure): PaginatedResult
    {
        if (($failure->errors->first()?->context['reason'] ?? null) === 'execution_deadline_exceeded') {
            foreach ($result->errors as $error) {
                if (($error->context['reason'] ?? null) === 'execution_deadline_exceeded') {
                    return $result;
                }
            }
        }
        return self::copy($result, $this->config, $failure, $this->hasItems ? ResultStatus::PARTIAL : ResultStatus::FAILED);
    }

    public static function fromResult(ExecutionResult $result, ClientConfig $config): PaginatedResult
    {
        return self::copy($result, $config);
    }

    private static function copy(ExecutionResult $result, ClientConfig $config, ?ExecutionResult $failure = null, ?ResultStatus $status = null): PaginatedResult
    {
        return new PaginatedResult(
            data: $result->data,
            status: $status ?? $result->status,
            errors: $failure === null ? $result->errors : new ErrorCollection([...$result->errors->all(), ...$failure->errors->all()]),
            validationErrors: $result->validationErrors,
            debug: $result->debug,
            traceId: $result->traceId,
            audit: $result->audit,
            meta: $result->meta,
            nested: $result->nested,
            requestClass: $result->requestClass,
            exception: $result->exception ?? $failure?->exception,
            response: $result->response,
            redaction: SensitiveFields::policy($config->redaction, $result->response !== null ? $result->response->request : $result->debug?->preparedRequest),
            exceptionFactory: $result->exceptionFactory,
            localization: $result->localization(),
            trace: $result->trace,
        );
    }
}
