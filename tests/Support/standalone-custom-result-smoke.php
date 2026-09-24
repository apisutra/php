<?php

declare(strict_types=1);

require_once __DIR__ . '/documentation-guides.php';

$checkout = $argv[1] ?? dirname(__DIR__, 2);
$example = $checkout . '/docs/example/custom-result';

// Проверяем опубликованный сценарий, включая ошибки и отсутствие повторного HTTP.
ob_start();
require $example . '/run.php';
$actual = json_decode((string) ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
$expected = json_decode(
    (string) file_get_contents($example . '/fixtures/expected.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
if ($actual !== $expected) {
    throw new RuntimeException('Пример собственного результата не соответствует контракту');
}

// Фрагменты рецепта должны совпадать с исполняемыми исходниками примера.
$source = '';
foreach (['run.php', 'src/SdkResult.php', 'src/SdkResultFactory.php'] as $file) {
    $source .= file_get_contents($example . '/' . $file) . "\n";
}
foreach (documentationGuides($checkout, 'guides/recipes/custom-result') as $language => $guide) {
    preg_match_all('/```php\R(.*?)\R```/s', $guide, $matches);
    foreach ($matches[1] as $block) {
        preg_match_all('/^use [^;]+;$/m', $block, $imports);
        foreach ($imports[0] as $import) {
            if (!str_contains($source, $import)) {
                throw new RuntimeException('Импорт рецепта отсутствует в примере: ' . $import);
            }
        }
        $code = trim((string) preg_replace('/^use [^;]+;\R/m', '', $block));
        if (!str_contains(documentationPhp($source), documentationPhp($code))) {
            throw new RuntimeException('Фрагмент рецепта расходится с примером собственного результата');
        }
    }
}

echo "Standalone custom result — OK.\n";
