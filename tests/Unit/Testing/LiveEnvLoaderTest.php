<?php

declare(strict_types=1);

use ApiSutra\Testing\LiveEnvLoader;

beforeEach(function (): void {
    putenv('LIVE_ENV_LOADER_TEST_KEY');
    putenv('LIVE_ENV_LOADER_TEST_KEY2');
    unset($_ENV['LIVE_ENV_LOADER_TEST_KEY'], $_ENV['LIVE_ENV_LOADER_TEST_KEY2']);
    unset($_SERVER['LIVE_ENV_LOADER_TEST_KEY'], $_SERVER['LIVE_ENV_LOADER_TEST_KEY2']);
});

describe('LiveEnvLoader', function () {
    it('ничего не делает, если файл отсутствует', function (): void {
        LiveEnvLoader::load(sys_get_temp_dir() . '/__nonexistent_apisutra_live_' . bin2hex(random_bytes(4)));

        expect(getenv('LIVE_ENV_LOADER_TEST_KEY'))->toBeFalse();
    });

    it('загружает переменные из файла', function (): void {
        $file = sys_get_temp_dir() . '/live_env_loader_' . bin2hex(random_bytes(4)) . '.env';
        file_put_contents($file, "LIVE_ENV_LOADER_TEST_KEY=value1\nLIVE_ENV_LOADER_TEST_KEY2=value2\n");

        try {
            LiveEnvLoader::load($file);

            expect(getenv('LIVE_ENV_LOADER_TEST_KEY'))->toBe('value1')
                ->and(getenv('LIVE_ENV_LOADER_TEST_KEY2'))->toBe('value2')
                ->and($_ENV['LIVE_ENV_LOADER_TEST_KEY'] ?? null)->toBe('value1')
                ->and($_SERVER['LIVE_ENV_LOADER_TEST_KEY'] ?? null)->toBe('value1');
        } finally {
            @unlink($file);
        }
    });

    it('не перезаписывает уже заданные переменные', function (): void {
        putenv('LIVE_ENV_LOADER_TEST_KEY=existing');
        $_ENV['LIVE_ENV_LOADER_TEST_KEY'] = 'existing';
        $_SERVER['LIVE_ENV_LOADER_TEST_KEY'] = 'existing';

        $file = sys_get_temp_dir() . '/live_env_loader_' . bin2hex(random_bytes(4)) . '.env';
        file_put_contents($file, "LIVE_ENV_LOADER_TEST_KEY=from_file\n");

        try {
            LiveEnvLoader::load($file);

            expect(getenv('LIVE_ENV_LOADER_TEST_KEY'))->toBe('existing');
        } finally {
            @unlink($file);
        }
    });

    it('игнорирует пустые строки и комментарии', function (): void {
        $file = sys_get_temp_dir() . '/live_env_loader_' . bin2hex(random_bytes(4)) . '.env';
        file_put_contents($file, "\n# comment\nLIVE_ENV_LOADER_TEST_KEY=ok\n  \n");

        try {
            LiveEnvLoader::load($file);

            expect(getenv('LIVE_ENV_LOADER_TEST_KEY'))->toBe('ok');
        } finally {
            @unlink($file);
        }
    });

    it('loadForTests находит .env.live.local в родительской директории', function (): void {
        $tmpDir = sys_get_temp_dir() . '/apisutra_live_env_' . bin2hex(random_bytes(4));
        $testsDir = $tmpDir . '/tests';
        $envFile = $tmpDir . '/.env.live.local';
        mkdir($testsDir, 0777, true);
        file_put_contents($envFile, "LIVE_ENV_LOADER_TEST_KEY=from_load_for_tests\n");

        try {
            LiveEnvLoader::loadForTests($testsDir);

            expect(getenv('LIVE_ENV_LOADER_TEST_KEY'))->toBe('from_load_for_tests');
        } finally {
            @unlink($envFile);
            rmdir($testsDir);
            rmdir($tmpDir);
        }
    });
});
