<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Continuation;

use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Enums\Continuation\ContinuationMode;
use ApiSutra\Serialization\VO\RequestPartsBag;
use ApiSutra\VO\Pipeline\PipelineContext;

/**
 * Провайдерный контракт маппинга ContinuationMode в транспортные поля запроса.
 *
 * Назначение:
 * - отделить core-mode (`Auto|Sync|Async`) от конкретного протокола провайдера
 *   (`body.async`, `query.mode`, custom header и т.д.).
 *
 * Инварианты:
 * - реализация должна работать только с RequestPartsBag и не выполнять I/O;
 * - при конфликте mode и ручных provider-флагов рекомендуется fail-fast исключение.
 *
 * @see docs/guides/provider-async-await.md
 * @see docs/guides/serialization.md
 */
interface ContinuationModeApplicatorInterface
{
    public function apply(
        RequestInterface $request,
        RequestPartsBag $parts,
        ContinuationMode $mode,
        ?PipelineContext $context = null,
    ): RequestPartsBag;
}
