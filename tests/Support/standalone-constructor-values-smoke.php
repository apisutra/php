<?php

declare(strict_types=1);

$checkout = $argv[1] ?? dirname(__DIR__, 2);
ob_start();
require $checkout . '/docs/example/constructor-values/run.php';
$actual = json_decode(ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
$expected = [
    'type' => 'record', 'permissions' => ['read', 'write'], 'flags' => ['visible' => true, 'archived' => false],
    'conflict' => 'constructor_value_mismatch', 'missing' => 'required_field_missing', 'allowMissing' => ['read', 'write'],
];
if ($actual !== $expected) {
    throw new LogicException('Пример constructorValue расходится с контрактом');
}
echo "Constructor values standalone smoke passed\n";
