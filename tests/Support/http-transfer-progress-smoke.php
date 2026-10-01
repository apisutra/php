<?php

declare(strict_types=1);

$checkout = $argv[1] ?? dirname(__DIR__, 2);
$result = require $checkout . '/docs/example/transfer-progress/run.php';
if ($result !== ['uploadBytes' => 262144, 'retryAttempt' => 2, 'downloadBytes' => 262144, 'unknownTotal' => null]) {
    throw new RuntimeException('Нарушен публичный пример прогресса HTTP');
}
echo "Пример upload/download/retry/unknown total: OK.\n";
