<?php

declare(strict_types=1);

namespace ApiSutra\VO\Metadata;

use ApiSutra\Contracts\Interfaces\DataTransfer\ResultMeta;

/**
 * Метаданные batch-операции (массовое выполнение запросов).
 *
 * Содержит статистику выполнения batch: общее количество запросов,
 * успешных, неудачных и частично выполненных.
 *
 * Используется в:
 * - BatchExecutor::execute() - создаёт после выполнения batch запросов
 * - BatchResult::meta() - хранит метаданные batch результата
 * - ResultCollection::toBatchMeta() - создаёт из коллекции результатов
 */
readonly class BatchMeta implements ResultMeta
{
    /**
     * @param int $total Общее количество запросов в batch
     * @param int $successful Количество успешно выполненных запросов
     * @param int $failed Количество неудачных запросов
     * @param int $partial Количество частично выполненных запросов (с предупреждениями)
     */
    public function __construct(
        public int $total,
        public int $successful,
        public int $failed,
        public int $partial = 0,
    ) {
    }
}
