<?php

declare(strict_types=1);

namespace Example\TransferProgress;

use ApiSutra\VO\Http\TransferProgress;
use RuntimeException;

/** Только последний снимок: без истории, I/O и обращения к SDK в callback. */
final class LatestProgress
{
    private ?TransferProgress $latest = null;

    public function take(): TransferProgress
    {
        $snapshot = $this->latest;
        $this->latest = null;
        if ($snapshot === null) {
            throw new RuntimeException('Передача не прислала прогресс');
        }
        return $snapshot;
    }

    public function __invoke(TransferProgress $progress): void
    {
        $this->latest = $progress;
    }
}
