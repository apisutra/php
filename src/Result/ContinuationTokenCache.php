<?php

declare(strict_types=1);

namespace ApiSutra\Result;

/**
 * Локальный кэш extracted continuation token на уровне одного ResolvedResult.
 *
 * Инварианты:
 * - extractor вызывается максимум один раз;
 * - null-результат тоже кэшируется;
 * - кэш не разделяется между разными результатами.
 *
 * @see docs/guides/continuation-token.md
 */
final class ContinuationTokenCache
{
    private bool $resolved = false;
    private ?string $token = null;

    public function get(
        ExecutionResult $result,
        ?ContinuationTokenExtractorInterface $extractor,
    ): ?string {
        if (!$this->resolved) {
            $this->token = $extractor?->extract($result);
            $this->resolved = true;
        }

        return $this->token;
    }
}
