<?php

declare(strict_types=1);

namespace ApiSutra\VO\Audit;

use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;

/**
 * Отладочная информация о выполнении запроса.
 *
 * Содержит подготовленный запрос, ответ провайдера, время выполнения
 * и необязательные вложенные снимки расширений. Штатный pipeline хранит
 * диагностику детей в ExecutionResult::$nested, без второго дерева DebugInfo.
 *
 * Используется в:
 * - ExecutionResultBuilder::buildSuccessResult() - создаётся при успешном выполнении
 * - ExecutionResultBuilder::buildExceptionResult() - создаётся при критической ошибке
 * - ExecutionScope::finish() - фиксирует длительность текущего исполнения
 */
readonly class DebugInfo
{
    /**
     * @param PreparedRequest|null $preparedRequest Подготовленный HTTP-запрос (метод, URL, заголовки, тело)
     * @param ProviderResponse|null $response Ответ от провайдера (статус, заголовки, тело)
     * @param float|null $duration Время выполнения запроса в миллисекундах
     * @param array<DebugInfo> $nested Дополнительные снимки; pipeline не заполняет их автоматически
     */
    public function __construct(
        public ?PreparedRequest $preparedRequest = null,
        public ?ProviderResponse $response = null,
        public ?float $duration = null,
        public array $nested = [],
    ) {
    }
}
