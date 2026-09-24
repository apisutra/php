<?php

declare(strict_types=1);

namespace ApiSutra\VO\Metadata;

use ApiSutra\Enums\Result\ResultStatus;

/**
 * Сводка по результатам выполнения.
 */
readonly class ResultSummary
{
    public function __construct(
        public int $total,
        public int $successful,
        public int $failed,
        public int $partial,
        public ResultStatus $status,
    ) {
    }

    public static function fromCounts(int $successful, int $failed, int $partial): self
    {
        $total = $successful + $failed + $partial;
        $status = match (true) {
            $total > 0 && $successful === $total => ResultStatus::SUCCESS,
            $total > 0 && $failed === $total => ResultStatus::FAILED,
            default => ResultStatus::PARTIAL,
        };
        return new self($total, $successful, $failed, $partial, $status);
    }

    public function isAllSuccess(): bool
    {
        return $this->total > 0 && $this->successful === $this->total;
    }

    public function isAllFailed(): bool
    {
        return $this->total > 0 && $this->failed === $this->total;
    }

    public function isAllPartial(): bool
    {
        return $this->total > 0 && $this->partial === $this->total;
    }
}
