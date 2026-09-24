<?php

declare(strict_types=1);

use ApiSutra\VO\Files\FileInput;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use GuzzleHttp\Psr7\PumpStream;
use GuzzleHttp\Psr7\Utils as Psr7Utils;

describe('FileInput', function () {
    it('переименовывает файл без копирования потока и сохраняет его владение', function (): void {
        $original = FileInput::fromContent('data', 'temp')->withMimeType('text/plain');
        $original->stream->read(1);
        $renamed = $original->withFilename('invoice.csv');
        expect($renamed)->not->toBe($original)->and($original->filename)->toBe('temp')
            ->and($renamed->filename)->toBe('invoice.csv')->and($renamed->size)->toBe(4)
            ->and($renamed->mimeType)->toBe('text/plain')->and($renamed->stream)->toBe($original->stream)
            ->and($renamed->stream->tell())->toBe(1);
        $renamed->close();
        expect($original->stream->isReadable())->toBeFalse();

        $borrowed = Psr7Utils::streamFor('borrowed');
        try {
            FileInput::fromStream($borrowed, 'temp')->withFilename('external.txt')->close();
            expect($borrowed->isReadable())->toBeTrue();
        } finally {
            $borrowed->close();
        }
    });

    it('создаёт объект из контента', function () {
        $file = FileInput::fromContent('data', 'file.txt');

        expect($file->filename)->toBe('file.txt');
        expect($file->mimeType)->toBeNull();
        expect($file->size)->toBe(4);
        expect((string) $file->stream)->toBe('data');
    });

    it('создаёт копию с mimeType', function () {
        $file = FileInput::fromContent('data', 'file.txt');
        $updated = $file->withMimeType('text/plain');

        expect($updated->mimeType)->toBe('text/plain');
        expect($updated->filename)->toBe('file.txt');
        expect($updated->size)->toBe(4);
    });

    it('бросает исключение при отсутствии файла', function () {
        set_error_handler(static fn (int $severity): bool => $severity === E_WARNING);
        try {
            expect(fn () => FileInput::fromPath('/tmp/no-such-file-' . uniqid() . '.bin'))
                ->toThrow(ConfigurationException::class);
        } finally {
            restore_error_handler();
        }
    });

    it('tryFromPath возвращает FileInput для существующего файла', function () {
        $tmp = tempnam(sys_get_temp_dir(), 'apisutra_');
        if ($tmp === false) {
            return;
        }
        file_put_contents($tmp, 'test-content');
        try {
            $file = FileInput::tryFromPath($tmp);
            expect($file)->not->toBeNull()
                ->and($file->filename)->toBe(basename($tmp))
                ->and((string) $file->stream)->toBe('test-content');
        } finally {
            @unlink($tmp);
        }
    });

    it('tryFromPath возвращает null при отсутствии файла', function () {
        set_error_handler(static fn (int $severity): bool => $severity === E_WARNING);
        try {
            $result = FileInput::tryFromPath('/tmp/no-such-file-' . uniqid() . '.bin');
            expect($result)->toBeNull();
        } finally {
            restore_error_handler();
        }
    });

    it('создаёт объект из разных типов stream', function () {
        $streams = [
            Psr7Utils::streamFor('data'),
            Psr7Utils::streamFor(fopen('php://temp', 'rb+')),
            new PumpStream(static fn (): string => ''),
        ];

        foreach ($streams as $index => $stream) {
            $file = FileInput::fromStream($stream, 'file-' . $index . '.bin');
            expect($file->stream)->toBe($stream);
            expect($file->size)->toBeNull();
        }
    });
});
