<?php

declare(strict_types=1);

namespace ApiSutra\Result;

/**
 * Provider-специфичная стратегия извлечения continuation token.
 *
 * Контракт:
 * - метод не должен бросать исключения для "токен отсутствует";
 * - при отсутствии/невалидности токена возвращает null;
 * - не выполняет сетевые операции и не меняет результат.
 *
 * @see docs/guides/continuation-token.md
 * @see docs/guides/provider-async-await.md
 */
interface ContinuationTokenExtractorInterface
{
    public function extract(ExecutionResult $result): ?string;
}
