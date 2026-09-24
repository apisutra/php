<?php

declare(strict_types=1);

$checkout = $argv[1] ?? dirname(__DIR__, 2);
$example = $checkout . '/docs/example/result-errors';

// Выполняем тот же файл, который поставляется пользователю, без тестовых подмен SDK.
ob_start();
require $example . '/run.php';
$actual = json_decode((string) ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
$expected = json_decode(
    (string) file_get_contents($example . '/fixtures/expected.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
if ($actual !== $expected) {
    throw new RuntimeException('Returns и фабрика исключений: пример нарушил ожидаемый контракт');
}

echo "Standalone result exceptions — OK.\n";
