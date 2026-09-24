<?php

declare(strict_types=1);

use ApiSutra\Config\ArchiveConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Extensions\Archive\Temp\ArchiveTempFileManager;

describe('ArchiveTempFileManager', function () {
    it('ограничивает размер архива', function () {
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            archive: new ArchiveConfig(maxSize: 3),
            environment: Environment::Testing,
        );
        $manager = new ArchiveTempFileManager($config);

        expect(fn () => $manager->createFromContent('1234'))
            ->toThrow(ConfigurationException::class);
    });

    it('создаёт временный файл и очищает его', function () {
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            archive: new ArchiveConfig(maxSize: 1024),
            environment: Environment::Testing,
        );
        $manager = new ArchiveTempFileManager($config);

        $path = $manager->createFromContent('zip-content', '.zip');

        expect(file_exists($path))->toBeTrue()
            ->and(file_get_contents($path) ?: '')->toBe('zip-content');

        $manager->cleanupOrFail($path);
        expect(file_exists($path))->toBeFalse();
    });
});
