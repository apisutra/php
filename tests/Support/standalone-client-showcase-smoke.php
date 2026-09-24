<?php

declare(strict_types=1);

require_once __DIR__ . '/documentation-guides.php';

$checkout = $argv[1] ?? dirname(__DIR__, 2);
$example = $checkout . '/docs/example/client-showcase';

// Запускаем опубликованный сценарий и сверяем наблюдаемое поведение с фиксированным контрактом.
ob_start();
require $example . '/run.php';
$actual = json_decode((string) ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
$expected = json_decode(
    (string) file_get_contents($example . '/fixtures/expected.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
if ($actual !== $expected) {
    throw new RuntimeException('Обзор клиента: настройки не дали ожидаемого поведения');
}

// Каждый фрагмент обзора исполняется в run.php; импорты в нём собраны в начале файла.
$source = (string) file_get_contents($example . '/run.php');
foreach (documentationGuides($checkout, 'guides/client/showcase') as $language => $guide) {
    preg_match_all('/```php\R(.*?)\R```/s', $guide, $matches);
    foreach ($matches[1] as $block) {
        preg_match_all('/^use [^;]+;$/m', $block, $imports);
        foreach ($imports[0] as $import) {
            if (!str_contains($source, $import)) {
                throw new RuntimeException('Импорт обзора отсутствует в примере: ' . $import);
            }
        }
        $code = trim((string) preg_replace('/^use [^;]+;\R/m', '', $block));
        if (!str_contains(documentationPhp($source), documentationPhp($code))) {
            throw new RuntimeException('Фрагмент обзора клиента расходится с исполняемым примером');
        }
    }
}

echo "Standalone client showcase — OK.\n";
