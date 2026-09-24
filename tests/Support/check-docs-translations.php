<?php

declare(strict_types=1);

require_once __DIR__ . '/documentation-guides.php';

$checkout = $argv[1] ?? dirname(__DIR__, 2);
$registry = json_decode(
    (string) file_get_contents($checkout . '/docs/translations.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$errors = [];
foreach ($registry['pages'] as $page) {
    $baseline = null;
    foreach ($page['paths'] as $language => $path) {
        if (!is_file($checkout . '/' . $path)) {
            $errors[] = $page['id'] . '/' . $language . ': отсутствует файл';
            continue;
        }
        $body = (string) file_get_contents($checkout . '/' . $path);
        preg_match_all('/```php\R(.*?)\R```/s', $body, $matches);
        $blocks = array_map(documentationPhp(...), $matches[1]);
        if ($baseline === null) {
            $baseline = $blocks;
        } elseif ($blocks !== $baseline) {
            $errors[] = $page['id'] . '/' . $language . ': PHP-фрагменты различаются за пределами комментариев';
        }
    }
}
echo json_encode(
    ['pages' => count($registry['pages']), 'errors' => $errors],
    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
) . PHP_EOL;
exit($errors === [] ? 0 : 1);
