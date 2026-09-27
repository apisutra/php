<?php

declare(strict_types=1);

$checkout = $argv[1] ?? dirname(__DIR__, 2);
$result = require $checkout . '/docs/example/polymorphic-json/run.php';
if ($result['same_model'] !== true || $result['errors']['polling_permissions'] !== 'invalid_list_shape') {
    throw new RuntimeException('Нарушен публичный контракт вариантов JSON без dev-зависимостей');
}
echo "Standalone polymorphic JSON: HTTP, polling, webhook, fallback и guards работают.\n";
