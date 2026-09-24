<?php

declare(strict_types=1);

namespace ApiSutra\Timing;

use ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use ApiSutra\Execution\Async\AsyncTask;

/** @internal Штатное ожидание уступает управление только внутри задачи SDK. */
final class CooperativeSleeper implements SleeperInterface
{
    public function sleepMs(int $milliseconds): void
    {
        if ($milliseconds <= 0) {
            return;
        }
        $task = AsyncTask::current();
        if ($task === null) {
            usleep($milliseconds * 1000);
        } else {
            $task->runtime->sleep($milliseconds);
        }
    }
}
