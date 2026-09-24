<?php

declare(strict_types=1);

namespace ApiSutra\Request;

use ApiSutra\Config\CooldownConfig;
use ApiSutra\Timing\ExecutionDeadline;
use ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use ApiSutra\Enums\Continuation\ContinuationMode;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Enums\Request\CredentialsMergeMode;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use ApiSutra\Pagination\PaginationRule;
use Psr\Http\Message\StreamInterface;

/**
 * Цепочка runtime-опций запроса.
 *
 * Нюансы:
 * - не мутирует исходный запрос, возвращает новое исполнение;
 * - overrides действуют только для текущего вызова.
 * - asProviderSync/asProviderAsync/asProviderAuto задают только mode-override,
 *   применение к протоколу провайдера выполняет continuation mode applicator.
 *
 * Рекомендации:
 * - постоянные настройки выносить в атрибуты и ClientConfig;
 * - runtime-override применять точечно, когда нужно переопределить поведение.
 *
 * @see docs/guides/requests.md
 * @see docs/guides/provider-async-await.md
 */
trait RequestOptionsChainTrait
{
    /**
     * Текущий снимок runtime-опций запроса.
     */
    abstract protected function currentOptions(): RequestOptions;

    /**
     * Создать исполнение запроса с указанными опциями.
     */
    abstract protected function executionFromOptions(RequestOptions $options): RequestExecutionInterface;

    /**
     * Полная замена набора опций.
     */
    public function withOptions(RequestOptions $options): RequestExecutionInterface
    {
        return $this->executionFromOptions($options);
    }

    public function withDeadline(ExecutionDeadline $deadline): RequestExecutionInterface
    {
        return $this->executionFromOptions($this->currentOptions()->withDeadline($deadline));
    }

    public function withoutDeadline(): RequestExecutionInterface
    {
        return $this->executionFromOptions($this->currentOptions()->withoutDeadline());
    }

    public function withRawResponse(?bool $enabled = true): RequestExecutionInterface
    {
        return $this->executionFromOptions($this->currentOptions()->withRawResponse($enabled));
    }

    /**
     * Задать назначение скачиваемого файла только для текущего вызова.
     */
    public function withDownloadTo(string|StreamInterface $target, bool $overwrite = false): RequestExecutionInterface
    {
        return $this->executionFromOptions($this->currentOptions()->withDownloadTo($target, $overwrite));
    }

    public function withoutDownloadTo(): RequestExecutionInterface
    {
        return $this->executionFromOptions($this->currentOptions()->withoutDownloadTo());
    }

    public function withUrl(string $url): RequestExecutionInterface
    {
        return $this->executionFromOptions($this->currentOptions()->withUrl($url));
    }

    public function withoutUrl(): RequestExecutionInterface
    {
        return $this->executionFromOptions($this->currentOptions()->withoutUrl());
    }

    public function withRequestEnrichers(bool $enabled = true): RequestExecutionInterface
    {
        return $this->executionFromOptions($this->currentOptions()->withRequestEnrichers($enabled));
    }

    /** Переопределить base URL для относительного endpoint. */
    public function withBaseUrl(string $url): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withBaseUrl($url),
        );
    }

    /** Задать пространство кеша только для текущего исполнения. */
    public function withCacheScope(string $scope): RequestExecutionInterface
    {
        return $this->executionFromOptions($this->currentOptions()->withCacheScope($scope));
    }

    /**
     * Полностью отключить кеширование для запроса.
     */
    public function withoutCache(): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withoutCache(),
        );
    }

    /**
     * Включить кеширование и опционально задать TTL.
     */
    public function withCache(?int $ttl = null): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withCache($ttl),
        );
    }

    /**
     * Выполнить запрос без чтения из кеша, но с записью.
     */
    public function withCacheWriteOnly(?int $ttl = null): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withCacheWriteOnly($ttl),
        );
    }

    /**
     * Выполнить запрос с чтением из кеша, но без записи.
     */
    public function withCacheReadOnly(?int $ttl = null): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withCacheReadOnly($ttl),
        );
    }

    /**
     * Отключить retry для запроса.
     */
    public function withoutRetry(): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withoutRetry(),
        );
    }

    /**
     * Включить retry и задать число попыток (для идемпотентных запросов).
     */
    public function withRetry(int $attempts): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withRetry($attempts),
        );
    }

    /**
     * Отключить auth для запроса, заменив предыдущий runtime-выбор.
     */
    public function withoutAuth(): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withoutAuth(),
        );
    }

    /**
     * Включить auth для запроса (не пробивает #[NoAuth]).
     */
    public function withAuth(): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withAuth(),
        );
    }

    /**
     * Включить auth и выбрать scope (не пробивает #[NoAuth]).
     */
    public function withAuthScope(string $scope): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withAuthScope($scope),
        );
    }

    /**
     * Принудительно включить auth (пробивает #[NoAuth]/withoutAuth()).
     *
     * Рекомендация: использовать только для исключительных кейсов.
     */
    public function forceAuth(): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->forceAuth(),
        );
    }

    /**
     * Принудительно включить auth и выбрать scope (пробивает #[NoAuth]/withoutAuth()).
     *
     * Рекомендация: использовать только для исключительных кейсов.
     */
    public function forceAuthScope(string $scope): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->forceAuthScope($scope),
        );
    }

    /**
     * Добавить задержку перед запросом (мс).
     */
    public function withDelay(int $ms): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withDelay($ms),
        );
    }

    /**
     * Убрать задержку запроса.
     */
    public function withoutDelay(): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withoutDelay(),
        );
    }

    /**
     * Установить ключ идемпотентности для запроса.
     */
    public function withIdempotencyKey(string $key): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withIdempotencyKey($key),
        );
    }

    /**
     * Переопределить серверный cooldown для запроса.
     */
    public function withCooldown(CooldownConfig $config): RequestExecutionInterface
    {
        return $this->executionFromOptions($this->currentOptions()->withCooldown($config));
    }

    /** Переопределить rate limit для запроса. */
    public function withRateLimit(
        int $limit,
        int $period,
        RateLimitBehavior $behavior = RateLimitBehavior::Wait,
        ?string $key = null,
    ): RequestExecutionInterface {
        return $this->executionFromOptions(
            $this->currentOptions()->withRateLimit($limit, $period, $behavior, $key),
        );
    }

    /**
     * Отключить rate limit для запроса.
     */
    public function withoutRateLimit(): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withoutRateLimit(),
        );
    }

    /**
     * Переопределить таймауты запроса.
     */
    public function withTimeout(int $seconds, ?int $connectTimeout = null): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withTimeout($seconds, $connectTimeout),
        );
    }

    /**
     * Установить traceId для корреляции запросов.
     */
    public function withTraceId(string $traceId): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withTraceId($traceId),
        );
    }

    /**
     * Явно задать роль запроса в пайплайне (обычно используется ядром).
     */
    public function withRole(RequestRole $role): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withRole($role),
        );
    }

    /**
     * Добавить или переопределить header для запроса.
     */
    public function withHeader(string $name, string $value): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withHeader($name, $value),
        );
    }

    /**
     * Переопределить правило пагинации для запроса.
     */
    public function rules(PaginationRule $rule): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withPaginationRule($rule),
        );
    }

    /**
     * Явно включить/выключить provider credentials enrichment.
     */
    public function withCredentialsEnrichment(bool $enabled = true): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withCredentialsEnrichment($enabled),
        );
    }

    /**
     * Явно отключить provider credentials enrichment.
     */
    public function withoutCredentialsEnrichment(): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withoutCredentialsEnrichment(),
        );
    }

    /**
     * Переопределить merge mode provider credentials enrichment.
     */
    public function withCredentialsMergeMode(CredentialsMergeMode $mode): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withCredentialsMergeMode($mode),
        );
    }

    /**
     * Переопределить scope provider credentials enrichment.
     */
    public function withCredentialsScope(string $scope): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withCredentialsScope($scope),
        );
    }

    /**
     * Переопределить режим provider-async.
     */
    public function withContinuationMode(ContinuationMode $mode): RequestExecutionInterface
    {
        return $this->executionFromOptions(
            $this->currentOptions()->withContinuationMode($mode),
        );
    }

    /**
     * Запросить принудительный sync-режим у провайдера.
     */
    public function asProviderSync(): RequestExecutionInterface
    {
        return $this->withContinuationMode(ContinuationMode::Sync);
    }

    /**
     * Запросить принудительный async-режим у провайдера.
     */
    public function asProviderAsync(): RequestExecutionInterface
    {
        return $this->withContinuationMode(ContinuationMode::Async);
    }

    /**
     * Разрешить авто-выбор режима у провайдера.
     */
    public function asProviderAuto(): RequestExecutionInterface
    {
        return $this->withContinuationMode(ContinuationMode::Auto);
    }
}
