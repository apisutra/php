<?php

declare(strict_types=1);

use ApiSutra\RateLimiting\Backends\PhpRedisRateLimitBackend;
use ApiSutra\RateLimiting\RateLimitQuota;
use ApiSutra\RateLimiting\RateLimiter;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use ApiSutra\Exceptions\Request\RateLimitException;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Timing\ExecutionBudget;

require __DIR__ . '/../../vendor/autoload.php';
[$script, $host, $port, $scope, $barrier, $worker, $limit, $period] = $argv;
$redis = new Redis();
$redis->connect($host, (int) $port, 2);
$backend = new PhpRedisRateLimitBackend($redis, $scope);
file_put_contents($barrier . '/' . $worker . '.ready', '1');
$deadline = microtime(true) + 10;
while (!is_file($barrier . '/go')) {
    if (microtime(true) > $deadline) { throw new RuntimeException('Не открыт тестовый барьер'); }
    usleep(1000);
}
$clock = new VirtualClock();
$clock->wallTime += ((int) $worker - 4) * 100000;
$operation = 'operation-' . ((int) $worker % 2);
try {
    (new RateLimiter($clock, $clock, $backend))->acquireAll([
        new RateLimitQuota('common', (int) $limit, (int) $period),
        new RateLimitQuota($operation, 2, (int) $period),
    ], ['common' => RateLimitBehavior::Throw, $operation => RateLimitBehavior::Throw], new ExecutionBudget($clock, 3000));
    $granted = true;
} catch (RateLimitException $exception) {
    $granted = false;
}
echo json_encode(['granted' => $granted, 'wallTime' => $clock->wallTime], JSON_THROW_ON_ERROR);
