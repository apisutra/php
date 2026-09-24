<?php

declare(strict_types=1);

namespace ApiSutra\Result;

use ApiSutra\Contracts\Interfaces\Localization\LocalizableExceptionInterface;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Localization\Message;
use ApiSutra\Continuation\ContinuationAwaitOptions;
use ApiSutra\Continuation\ContinuationOutcome;
use ApiSutra\Continuation\ContinuationService;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Exceptions\Configuration\ContinuationConfigurationException;
use ApiSutra\Request\RequestSpecResolver;

/**
 * Готовый результат выполнения запроса и его представления.
 *
 * Нюансы:
 * - await()/awaitAs() кешируют итог в рамках одного handle и не запускают повторный polling.
 * - awaitAs() гидратирует сохранённый Ready-payload при смене DTO-типа.
 * - Continuation orchestration доступен только если handle создан с client/sourceRequest контекстом.
 *
 * @see docs/guides/provider-async-await.md
 * @see docs/guides/errors.md
 */
final class ResultHandle
{
    private ?ContinuationOutcome $awaitOutcome = null;
    private ?string $awaitType = null;

    public function __construct(
        private readonly ExecutionResult $result,
        private readonly ResolvedResultFactoryInterface $factory,
        private readonly ?ClientInterface $client = null,
        private readonly ?RequestInterface $sourceRequest = null,
    ) {
    }

    public function raw(): ExecutionResult
    {
        return $this->result;
    }

    public function resolved(): ResolvedResultInterface
    {
        return $this->factory->make($this->raw());
    }

    public function dataOrFail(): mixed
    {
        $result = $this->raw()->throw();
        return $result->data;
    }

    public function continuationToken(): ?string
    {
        return $this->resolved()->continuationToken();
    }

    public function continuationTokenOrFail(): string
    {
        try {
            return $this->resolved()->continuationTokenOrFail();
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->client?->getConfig()->localization ?? new LocalizationConfig());
        }
    }

    public function await(?ContinuationAwaitOptions $options = null): mixed
    {
        try {
            if ($this->awaitOutcome !== null) {
                return $this->awaitOutcome->value;
            }

            $this->awaitOutcome = $this->resolveContinuation($options);

            return $this->awaitOutcome->value;
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->client?->getConfig()->localization ?? new LocalizationConfig());
        }
    }

    /**
     * Дождаться финального результата и вернуть его в указанном DTO-типе.
     *
     * Если await уже выполнялся, метод не запускает polling повторно:
     * используется сохранённый payload до гидрации.
     */
    public function awaitAs(string $finalType, ?ContinuationAwaitOptions $options = null): mixed
    {
        try {
            $resolvedType = trim($finalType);
            if ($resolvedType === '') {
                throw new ContinuationConfigurationException(new Message('result.finaltype_must_not_be_empty'));
            }

            if ($this->awaitOutcome !== null && $this->awaitType === $resolvedType) {
                return $this->awaitOutcome->value;
            }

            if ($this->awaitOutcome !== null) {
                $mapped = $this->continuationService()->hydrateOutcome($this->awaitOutcome, $resolvedType);
                $this->awaitType = $resolvedType;
                $this->awaitOutcome = $mapped;

                return $mapped->value;
            }

            $this->awaitOutcome = $this->resolveContinuation($options, $resolvedType);

            return $this->awaitOutcome->value;
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->client?->getConfig()->localization ?? new LocalizationConfig());
        }
    }

    /**
     * Sugar для быстрого доступа к debug-снимку prepared request.
     *
     * @return array{
     *   method: string,
     *   url: string,
     *   headers: array<string, string>,
     *   bodyRaw: ?string,
     *   hasStream: bool
     * }|null
     */
    public function requestDebug(bool $redactSensitive = true): ?array
    {
        return $this->raw()->requestDebug($redactSensitive);
    }

    public function requestDebugJson(
        bool $redactSensitive = true,
        int $flags = JSON_UNESCAPED_UNICODE,
    ): ?string {
        return $this->raw()->requestDebugJson($redactSensitive, $flags);
    }

    private function continuationService(): ContinuationService
    {
        if ($this->client === null) {
            throw new ContinuationConfigurationException(
                new Message('result.continuation_orchestration_requires_a_resulthandle_client_context'),
            );
        }

        return $this->client->continuation();
    }

    private function resolveContinuation(
        ?ContinuationAwaitOptions $options,
        ?string $finalType = null,
    ): ContinuationOutcome {
        $outcome = $this->continuationService()->resolveFromStartResult(
            startResult: $this->raw(),
            sourceRequest: $this->sourceRequest,
            finalTypeOverride: $finalType,
            options: $options,
        );
        $request = $this->sourceRequest instanceof RequestExecutionInterface
            ? $this->sourceRequest->getRequest()
            : $this->sourceRequest;
        $declaredType = $request === null
            ? null
            : (new RequestSpecResolver())->resolveClass($request::class)->continuationResult?->finalType;
        $this->awaitType = $finalType ?? $declaredType;

        return $outcome;
    }
}
