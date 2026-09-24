<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Execution;

use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\Localization\Message;
use ApiSutra\Result\PoolSummary;
use Override;
use Throwable;

/** Авария обхода или обработки; выполненные элементы отражены в summary. */
final class PoolConsumptionException extends SdkException
{
    public function __construct(
        public readonly PoolSummary $summary,
        Throwable $previous,
        ?LocalizationConfig $localization = null,
    ) {
        parent::__construct(
            new Message('execution.pool_consumption_failed', ['reason' => $summary->terminationReason->value]),
            previous: $previous,
            localization: $localization,
        );
    }

    #[Override]
    protected function copyForLocalization(): static
    {
        return new self($this->summary, $this->getPrevious() ?? $this);
    }
}
