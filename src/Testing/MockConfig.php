<?php

declare(strict_types=1);

namespace ApiSutra\Testing;

final class MockConfig
{
    private static bool $throwOnMissingFixtures = false;
    private static ?string $fixturePath = null;

    public static function throwOnMissingFixtures(): void
    {
        self::$throwOnMissingFixtures = true;
    }

    public static function setFixturePath(string $path): void
    {
        self::$fixturePath = $path;
    }

    public static function getFixturePath(): ?string
    {
        return self::$fixturePath;
    }

    public static function shouldThrowOnMissingFixtures(): bool
    {
        return self::$throwOnMissingFixtures;
    }
}
