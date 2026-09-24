<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

/** Соединяет query как последовательность байтов, сохраняя повторяющиеся ключи. */
final class UrlQuery
{
    /** @return array{string, string} URL без query/fragment и исходный query. */
    public static function split(string $url): array
    {
        $url = explode('#', $url, 2)[0];
        $parts = explode('?', $url, 2);
        return [$parts[0], $parts[1] ?? ''];
    }

    public static function append(string $url, string ...$queries): string
    {
        [$path, $original] = self::split($url);
        $parts = [];
        foreach ([$original, ...$queries] as $query) {
            $query = trim($query, '&');
            if ($query !== '') {
                $parts[] = $query;
            }
        }
        return $parts === [] ? $path : $path . '?' . implode('&', $parts);
    }
}
