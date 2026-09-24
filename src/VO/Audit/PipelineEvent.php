<?php

declare(strict_types=1);

namespace ApiSutra\VO\Audit;

use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Diagnostics\ExecutionTrace;
use ApiSutra\Enums\Pipeline\PipelineStage;

/**
 * Событие выполнения этапа pipeline.
 *
 * Хранит информацию о прохождении конкретного этапа обработки запроса:
 * стадия, время начала/продолжительность, класс запроса, роль и payload.
 *
 * Используется в:
 * - ExecutionScope::record() - создаёт события для аудита выполнения pipeline
 * - ExecutionResult::$audit - массив событий для трассировки выполнения
 */
readonly class PipelineEvent
{
    /**
     * @param PipelineStage $stage Этап pipeline (Validation, Preparation, Execution и т.д.)
     * @param float $timestamp Время начала события (Unix timestamp с микросекундами)
     * @param float|null $duration Продолжительность этапа в миллисекундах (null если не завершён)
     * @param string|null $requestClass Класс запроса (FQCN)
     * @param RequestRole $role Роль запроса (Primary, Dependency, Auth, Background и т.д.)
     * @param mixed $payload Дополнительные данные этапа (ошибки, метаданные и т.д.)
     */
    public function __construct(
        public PipelineStage $stage,
        public float $timestamp,
        public ?float $duration,
        public ?string $requestClass,
        public RequestRole $role,
        public mixed $payload,
        public ?ExecutionTrace $trace = null,
        /** @var array<string, scalar|null> */
        public array $context = [],
    ) {
    }
}
