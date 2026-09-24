<?php

declare(strict_types=1);

use ApiSutra\VO\Files\FileResponse;
use GuzzleHttp\Psr7\Utils as Psr7Utils;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

describe('FileResponse', function () {
    it('распознаёт архив по mimeType', function () {
        $response = new FileResponse(
            stream: Psr7Utils::streamFor('PK' . 'zip'),
            mimeType: 'application/zip',
        );

        expect($response->isArchive())->toBeTrue();
    });

    it('не считает обычный контент архивом', function () {
        $response = new FileResponse(
            stream: Psr7Utils::streamFor('plain-text'),
            mimeType: 'text/plain',
        );

        expect($response->isArchive())->toBeFalse();
    });

    it('сохраняет файл потоково', function () {
        $content = str_repeat('A', 20000);
        $response = new FileResponse(
            stream: Psr7Utils::streamFor($content),
            mimeType: 'text/plain',
        );

        $path = sys_get_temp_dir() . '/apisutra-file-' . uniqid() . '.txt';

        try {
            $response->saveTo($path);
            expect(is_file($path))->toBeTrue();
            expect(file_get_contents($path))->toBe($content);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    });

    it('бросает исключение при невозможности сохранить файл', function () {
        $response = new FileResponse(
            stream: Psr7Utils::streamFor('data'),
            mimeType: 'text/plain',
        );

        set_error_handler(static fn (int $severity): bool => $severity === E_WARNING);
        try {
            expect(fn () => $response->saveTo('/root/forbidden-' . uniqid() . '.txt'))
                ->toThrow(ConfigurationException::class);
        } finally {
            restore_error_handler();
        }
    });
});
