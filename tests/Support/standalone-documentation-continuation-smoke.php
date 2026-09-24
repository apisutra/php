<?php

declare(strict_types=1);

$checkout = $argv[1] ?? dirname(__DIR__, 2);
ob_start();
require $checkout . '/docs/example/continuation/run.php';
$output = json_decode((string) ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
if ($output !== ['id' => 7, 'cached_same_object' => true, 'requests' => 3]) {
    throw new RuntimeException('Опубликованный пример continuation нарушил контракт');
}
echo "Standalone published continuation — OK.\n";
