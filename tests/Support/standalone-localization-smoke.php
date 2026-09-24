<?php

declare(strict_types=1);

$checkout = $argv[1] ?? dirname(__DIR__, 2);
ob_start();
require $checkout . '/docs/example/localization/run.php';
$output = json_decode((string) ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
if (
    $output['en']['code'] !== 'hydration_error' || $output['ru']['code'] !== 'hydration_error'
    || !str_starts_with($output['en']['message'], 'Invalid data')
    || !str_starts_with($output['ru']['message'], 'Некорректные данные')
    || $output['sdk'] !== 'Превышен лимит: 10' || $output['literal'] !== 'Provider-specific message'
) {
    throw new RuntimeException('Пример локализации нарушил контракт');
}
echo "Standalone localization — OK.\n";
