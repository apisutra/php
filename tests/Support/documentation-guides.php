<?php

declare(strict_types=1);

/** @return array<string, string> */
function documentationGuides(string $checkout, string $id): array
{
    $registry = json_decode(
        (string) file_get_contents($checkout . '/docs/translations.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    foreach ($registry['pages'] as $page) {
        if ($page['id'] !== $id) {
            continue;
        }
        $guides = [];
        foreach ($registry['languages'] as $language => $config) {
            $path = $page['paths'][$language] ?? null;
            if ($path === null && !$config['required']) {
                continue;
            }
            if ($path === null || !is_file($checkout . '/' . $path)) {
                throw new RuntimeException('Отсутствует редакция руководства: ' . $id . '/' . $language);
            }
            $guides[$language] = (string) file_get_contents($checkout . '/' . $path);
        }
        return $guides;
    }
    throw new RuntimeException('Неизвестное руководство: ' . $id);
}

function documentationPhp(string $code): string
{
    // Комментарии переводятся, но строковые литералы и все значимые токены сохраняются.
    $result = '';
    foreach (token_get_all('<?php ' . $code) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_OPEN_TAG, T_COMMENT, T_DOC_COMMENT, T_WHITESPACE], true)) {
                continue;
            }
            $result .= $token[0] . ':' . strlen($token[1]) . ':' . $token[1] . ';';
        } else {
            $result .= 'char:' . $token . ';';
        }
    }
    return $result;
}
