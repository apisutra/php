<?php

declare(strict_types=1);

namespace ApiSutra\Result;

use ApiSutra\Contracts\Interfaces\DataTransfer\ResultMeta;

/**
 * Provider-специфичная стратегия извлечения технической меты результата.
 *
 * Контракт:
 * - метод не должен бросать исключения для сценария "мета отсутствует";
 * - при отсутствии/невалидности меты возвращает null;
 * - не выполняет сетевые операции и не меняет состояние ExecutionResult.
 *
 * @see docs/guides/client-config/responses-errors.md
 * @see docs/guides/provider-methodology.md
 */
interface ResultMetaExtractorInterface
{
    public function extract(ExecutionResult $result): ?ResultMeta;
}
