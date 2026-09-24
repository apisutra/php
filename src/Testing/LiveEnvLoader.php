<?php

declare(strict_types=1);

namespace ApiSutra\Testing;

/**
 * Загрузка переменных из .env-файла в окружение для live-тестов.
 *
 * Используется в tests/bootstrap.php. Не перезаписывает уже заданные
 * переменные (приоритет у shell/env). Без зависимости от dotenv.
 */
final class LiveEnvLoader
{
    /**
     * Загружает переменные из файла в putenv/ENV/SERVER.
     * Файл отсутствует — ничего не делает. Существующие переменные не трогает.
     */
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eq));
            if ($key === '') {
                continue;
            }

            if (getenv($key) !== false) {
                continue;
            }

            $value = trim(substr($line, $eq + 1), " \t\"'");
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    /**
     * Загружает .env.live.local из корня пакета.
     * Удобно из tests/bootstrap.php: LiveEnvLoader::loadForTests(__DIR__)
     */
    public static function loadForTests(string $testsDir): void
    {
        self::load(dirname($testsDir) . '/.env.live.local');
    }
}
