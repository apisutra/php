<?php

declare(strict_types=1);

namespace ApiSutra\Support;

use ApiSutra\Enums\DataTransfer\ValueState;

final class ArrayPath
{
    /** @var array<string, array<int, string>> */
    private static array $pathCache = [];

    public static function getByPath(mixed $data, string $path): mixed
    {
        if (!is_array($data)) {
            return null;
        }

        return self::traverse($data, self::segments($path));
    }

    public static function getByPathWithStatus(mixed $data, string $path): PathResult
    {
        if (!is_array($data)) {
            return new PathResult(ValueState::Missing, null);
        }

        $value = self::traverse($data, self::segments($path));

        if ($value === null) {
            // Проверяем, действительно ли отсутствует или просто null
            $exists = self::pathExists($data, self::segments($path));
            $state = $exists ? ValueState::Null : ValueState::Missing;
            return new PathResult($state, null);
        }

        return new PathResult(ValueState::Present, $value);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function setByPath(array &$data, string $path, mixed $value): void
    {
        $segments = self::segments($path);
        $current = &$data;

        foreach ($segments as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }

        $current = $value;
    }

    /**
     * @param array<int, string> $segments
     */
    private static function traverse(array $data, array $segments): mixed
    {
        return array_reduce(
            $segments,
            fn ($current, $segment) => is_array($current) && array_key_exists($segment, $current)
                ? $current[$segment]
                : null,
            $data
        );
    }

    /**
     * @param array<int, string> $segments
     */
    private static function pathExists(array $data, array $segments): bool
    {
        $current = $data;
        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return false;
            }
            $current = $current[$segment];
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private static function segments(string $path): array
    {
        return self::$pathCache[$path] ??= explode('.', $path);
    }
}
