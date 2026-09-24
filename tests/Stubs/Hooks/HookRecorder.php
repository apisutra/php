<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hooks;

final class HookRecorder
{
    /**
     * @var array<int, string>
     */
    private static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }

    public static function add(string $value): void
    {
        self::$calls[] = $value;
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return self::$calls;
    }
}
