<?php

declare(strict_types=1);

namespace ApiSutra\Result;

use ApiSutra\Diagnostics\SensitiveFields;
use ApiSutra\Contracts\Interfaces\Errors\ExecutionExceptionFactoryInterface;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Diagnostics\ExecutionTrace;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\ExceptionLocalization;
use ApiSutra\VO\Errors\RequestError;
use ApiSutra\Localization\Message;
use ApiSutra\Diagnostics\RedactionPolicy;
use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Contracts\Interfaces\Core\ResultInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResultMeta;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\VO\Audit\DebugInfo;
use ApiSutra\VO\Audit\PipelineEvent;
use ApiSutra\VO\Errors\ValidationError;
use ApiSutra\VO\Http\ProviderResponse;
use Override;
use Throwable;

/**
 * Результат выполнения запроса с данными, ошибками и аудитом.
 *
 * Нюансы:
 * - data может быть null даже при SUCCESS (например, пустой ответ).
 * - errors описывает ошибки выполнения/транспорта, validationErrors — отдельный список ошибок валидации.
 * - exception может отсутствовать, если ошибка зафиксирована только через errors.
 * - nested заполняется для composite/depends-on и доступен как массив и как коллекция.
 */
readonly class ExecutionResult implements ResultInterface
{
    private ResultCollection $nestedResults;
    public ?string $traceId;

    /**
     * @param array<ValidationError> $validationErrors Ошибки валидации входных данных.
     * @param array<PipelineEvent> $audit Хронология этапов пайплайна (может быть пустой).
     * @param array<ExecutionResult> $nested Результаты вложенных запросов (composite/depends-on).
     */
    public function __construct(
        public mixed $data,
        public ResultStatus $status,
        public ErrorCollection $errors,
        public array $validationErrors = [],
        public ?DebugInfo $debug = null,
        ?string $traceId = null,
        public array $audit = [],
        public ?ResultMeta $meta = null,
        public array $nested = [],
        public ?string $requestClass = null,
        public ?Throwable $exception = null,
        public ?ProviderResponse $response = null,
        private RedactionPolicy $redaction = new RedactionPolicy(),
        public ?ExecutionExceptionFactoryInterface $exceptionFactory = null,
        private LocalizationConfig $localization = new LocalizationConfig(),
        public ?ExecutionTrace $trace = null,
    ) {
        if ($trace !== null && $traceId !== null && $trace->traceId !== $traceId) {
            throw new ConfigurationException(new Message('diagnostics.conflicting_trace'));
        }
        $this->traceId = $trace->traceId ?? $traceId;
        $this->nestedResults = ResultCollection::make($this->nested);
    }

    /** @internal @param list<PipelineEvent> $audit */
    public function withDiagnostics(ExecutionTrace $trace, array $audit, ?DebugInfo $debug): static
    {
        return new ($this::class)(
            data: $this->data,
            status: $this->status,
            errors: $this->errors,
            validationErrors: $this->validationErrors,
            debug: $debug,
            traceId: $trace->traceId,
            audit: $audit,
            meta: $this->meta,
            nested: $this->nested,
            requestClass: $this->requestClass,
            exception: $this->exception,
            response: $this->response,
            redaction: $this->redaction,
            exceptionFactory: $this->exceptionFactory,
            localization: $this->localization,
            trace: $trace,
        );
    }

    /** @internal Дополнение завершения сохраняет диагностическую и публичную политику. */
    public function withMeta(ResultMeta $meta): static
    {
        return $this->copyCompletion($meta, $this->errors);
    }

    /** @internal Вторичная ошибка не заменяет уже зафиксированную первичную причину. */
    public function withErrors(ErrorCollection $errors): static
    {
        return $this->copyCompletion($this->meta, $errors);
    }

    private function copyCompletion(?ResultMeta $meta, ErrorCollection $errors): static
    {
        return new ($this::class)(
            data: $this->data,
            status: $this->status,
            errors: $errors,
            validationErrors: $this->validationErrors,
            debug: $this->debug,
            traceId: $this->traceId,
            audit: $this->audit,
            meta: $meta,
            nested: $this->nested,
            requestClass: $this->requestClass,
            exception: $this->exception,
            response: $this->response,
            redaction: $this->redaction,
            exceptionFactory: $this->exceptionFactory,
            localization: $this->localization,
            trace: $this->trace,
        );
    }

    public function localization(): LocalizationConfig
    {
        return $this->localization;
    }

    /** Новое представление сохраняет данные, исходный результат не меняется. */
    public function localized(LocalizationConfig $localization): static
    {
        $errors = array_map(static fn (RequestError $error): RequestError => $error->localized($localization), $this->errors->all());
        $nested = array_map(static fn (self $result): self => $result->localized($localization), $this->nested);
        $exception = $this->exception === null ? null : ExceptionLocalization::apply($this->exception, $localization);
        if (
            $this->localization->locale === $localization->locale
            && $this->localization->messages === $localization->messages
            && $errors === $this->errors->all() && $nested === $this->nested && $exception === $this->exception
        ) {
            return $this;
        }
        return new ($this::class)(
            data: $this->data,
            status: $this->status,
            errors: new ErrorCollection($errors),
            validationErrors: $this->validationErrors,
            debug: $this->debug,
            traceId: $this->traceId,
            audit: $this->audit,
            meta: $this->meta,
            nested: $nested,
            requestClass: $this->requestClass,
            exception: $exception,
            response: $this->response,
            redaction: $this->redaction,
            exceptionFactory: $this->exceptionFactory,
            localization: $localization,
            trace: $this->trace,
        );
    }

    /**
     * Успешное завершение, без анализа состава данных и ошибок.
     */
    #[Override]
    public function isSuccess(): bool
    {
        return $this->status === ResultStatus::SUCCESS;
    }

    /**
     * Частичный успех (обычно при composite с ошибками отдельных шагов).
     */
    public function isPartial(): bool
    {
        return $this->status === ResultStatus::PARTIAL;
    }

    /**
     * Провал выполнения, даже если data может быть заполнен частично.
     */
    #[Override]
    public function isFailed(): bool
    {
        return $this->status === ResultStatus::FAILED;
    }

    /**
     * Данные присутствуют, но это не гарантирует статус SUCCESS.
     */
    #[Override]
    public function hasData(): bool
    {
        return $this->data !== null;
    }

    /**
     * Ошибки выполнения/транспорта (не включает validationErrors как список полей).
     */
    #[Override]
    public function hasErrors(): bool
    {
        return $this->errors->first() !== null;
    }

    /**
     * Отдельный список ошибок валидации входных данных.
     */
    public function hasValidationErrors(): bool
    {
        return $this->validationErrors !== [];
    }

    /**
     * Возвращает первую ошибку валидации для поля, если есть.
     */
    public function validationErrorFor(string $field): ?ValidationError
    {
        foreach ($this->validationErrors as $error) {
            if ($error->field === $field) {
                return $error;
            }
        }

        return null;
    }

    /**
     * Бросает исключение только при FAILED, иначе возвращает self.
     */
    public function throw(): self
    {
        if ($this->isFailed()) {
            throw ResultExceptionSelector::select($this);
        }

        return $this;
    }

    /**
     * Краткое сообщение об ошибках: первая ошибка + количество при множественных.
     */
    public function message(): ?string
    {
        $first = $this->errors->first();
        $count = $first === null ? 0 : count($this->errors->all());

        return match (true) {
            $first === null => null,
            $count <= 1 => $first->message,
            default => (new Message('result.multiple_errors', ['count' => $count, 'value1' => $first->message]))->render($this->localization),
        };
    }

    /**
     * Возвращает вложенные результаты как коллекцию для удобной агрегации.
     */
    protected function nestedResults(): ResultCollection
    {
        return $this->nestedResults;
    }

    /**
     * Вернуть debug-снимок подготовленного HTTP-запроса.
     *
     * @return array{
     *   method: string,
     *   url: string,
     *   headers: array<string, string>,
     *   bodyRaw: ?string,
     *   body: mixed,
     *   query: array<string, mixed>|null,
     *   form: array<string, mixed>|null,
     *   hasStream: bool,
     *   oneOf: array<string, mixed>|null,
     *   credentialsEnrichment: array<string, mixed>|null
     * }|null
     */
    public function requestDebug(bool $redactSensitive = true): ?array
    {
        $prepared = $this->debug?->preparedRequest;
        if ($prepared === null) {
            return null;
        }

        $headers = $prepared->headers;
        $credentials = is_array($prepared->meta['credentialsEnrichment'] ?? null)
            ? $prepared->meta['credentialsEnrichment']
            : null;
        $secretFields = SensitiveFields::of($prepared);
        $secretKeys = $secretFields;
        $policy = $this->redaction->withFields($secretKeys);
        $bodySize = $prepared->body !== null ? strlen($prepared->body) : null;
        $bodyOmitted = $redactSensitive && $bodySize !== null && $bodySize > $policy->maxBodyBytes;
        $body = $bodyOmitted ? null : ($prepared->meta['body'] ?? null);
        $query = is_array($prepared->meta['query'] ?? null) ? $prepared->meta['query'] : null;
        $form = $this->extractForm($body, $headers);
        $bodyRaw = $bodyOmitted ? null : $prepared->body;
        $url = $prepared->url;
        if ($redactSensitive) {
            $headers = $policy->headers($headers);
            $url = $prepared->destination?->preserveUrl ? $prepared->destination->diagnosticUrl() : $policy->url($url);
            $body = $policy->data($body);
            $query = $query !== null ? $policy->query($query) : null;
            $form = $policy->data($form);
            $contentType = null;
            foreach ($prepared->headers as $name => $value) {
                if (strcasecmp($name, 'Content-Type') === 0) {
                    $contentType = $value;
                }
            }
            $bodyRaw = $policy->body($bodyRaw, $contentType);
        }

        $snapshot = [
            'method' => $prepared->method->value,
            'url' => $url,
            'headers' => $headers,
            'bodyRaw' => $bodyRaw,
            'bodySize' => $bodySize,
            'bodyOmitted' => $bodyOmitted || $prepared->stream !== null,
            'bodyOmissionReason' => $bodyOmitted ? 'body_size_limit' : ($prepared->stream !== null ? 'stream' : null),
            'body' => $body,
            'query' => $query,
            'form' => $form,
            'hasStream' => $prepared->stream !== null,
            'oneOf' => is_array($prepared->meta['oneOf'] ?? null)
                ? ($redactSensitive ? $policy->data($prepared->meta['oneOf']) : $prepared->meta['oneOf'])
                : null,
            'credentialsEnrichment' => $credentials,
        ];
        return $redactSensitive && $prepared->destination !== null
            ? $prepared->destination->redactReferences($snapshot) : $snapshot;
    }

    public function requestDebugJson(
        bool $redactSensitive = true,
        int $flags = JSON_UNESCAPED_UNICODE,
    ): ?string {
        $snapshot = $this->requestDebug($redactSensitive);
        if ($snapshot === null) {
            return null;
        }

        $encoded = json_encode($snapshot, $flags);

        return is_string($encoded) ? $encoded : null;
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function extractForm(mixed $body, array $headers): ?array
    {
        if (!is_array($body)) {
            return null;
        }

        $contentType = strtolower($headers['Content-Type'] ?? '');
        if (!str_contains($contentType, 'multipart/form-data')) {
            return null;
        }

        return $body;
    }
}
