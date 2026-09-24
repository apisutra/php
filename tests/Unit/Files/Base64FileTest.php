<?php

declare(strict_types=1);

use ApiSutra\VO\Files\Base64File;

describe('Base64File', function () {
    it('декодирует контент и считает размер', function () {
        $file = new Base64File(base64_encode('data'));

        expect($file->content())->toBe('data');
        expect($file->size())->toBe(4);
        expect((string) $file->stream())->toBe('data');
    });

    it('сохраняет файл на диск', function () {
        $file = new Base64File(base64_encode('data'));
        $path = tempnam(sys_get_temp_dir(), 'apisutra-');
        if ($path === false) {
            $path = sys_get_temp_dir() . '/apisutra-' . uniqid();
        }

        $file->saveTo($path);

        expect(file_get_contents($path) ?: '')->toBe('data');
        @unlink($path);
    });
});
