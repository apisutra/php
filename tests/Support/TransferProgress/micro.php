<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support\TransferProgress;

use ApiSutra\VO\Http\TransferProgress;
use ApiSutra\Diagnostics\ExecutionTrace;
use ApiSutra\Pipeline\Diagnostics\TransferObservation;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once __DIR__ . '/common.php';

$trace = ExecutionTrace::create('micro');
$warm = new TransferObservation(static function (): void {
});
$warmAttempt = $warm->attempt($trace, 1);
$warmAttempt->notify(1, 1, 0, 0);
$warmAttempt->close();
$warm->close();
unset($warmAttempt, $warm);
foreach ([10000, 100000, 1000000] as $events) {
    foreach (['direct', 'empty', 'latest'] as $mode) {
        $samples = [];
        $peaks = [];
        $retained = [];
        for ($run = 0; $run < 7; ++$run) {
            gc_collect_cycles();
            memory_reset_peak_usage();
            $before = memory_get_usage();
            $latest = null;
            $callback = $mode === 'latest' ? static function (TransferProgress $p) use (&$latest): void {
                $latest = $p;
            }
                : static function (): void {
                };
            $observation = $mode === 'direct' ? null : new TransferObservation($callback);
            $attempt = $observation?->attempt($trace, 1);
            $start = hrtime(true);
            for ($i = 1; $i <= $events; ++$i) {
                $mode === 'direct' ? $callback($events, $i, 0, 0) : $attempt->notify($events, $i, 0, 0);
            }
            $elapsed = (hrtime(true) - $start) / 1e6;
            $peak = memory_get_peak_usage() - $before;
            $held = memory_get_usage() - $before;
            $samples[] = $elapsed;
            $peaks[] = $peak;
            $retained[] = $held;
            $attempt?->close();
            $observation?->close();
            unset($attempt, $observation, $callback, $latest);
        }
        sort($samples);
        report('micro', ['mode' => $mode, 'events' => $events, 'runs' => 7, 'medianMs' => $samples[3],
            'minMs' => $samples[0], 'maxMs' => $samples[6],
            'peakBytes' => max($peaks), 'retainedBytes' => max($retained)]);
    }
}
// Много завершённых исполнений: нет накопления получателей и истории.
$before = memory_get_usage();
for ($i = 0; $i < 100000; ++$i) {
    $observation = new TransferObservation(static function (): void {
    });
    $attempt = $observation->attempt($trace, 1);
    $attempt->notify(10, 10, 0, 0);
    $attempt->close();
    $observation->close();
    unset($attempt, $observation);
}
gc_collect_cycles();
report('micro/lifecycle', ['executions' => 100000, 'retainedBytes' => memory_get_usage() - $before]);
