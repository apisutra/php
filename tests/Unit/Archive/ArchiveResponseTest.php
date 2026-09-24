<?php

declare(strict_types=1);

use ApiSutra\Extensions\Archive\Response\ArchiveResponse;
use ApiSutra\Extensions\Archive\Response\ArchiveEntry;

describe('ArchiveResponse', function () {
    it('читает zip архив и извлекает файл', function () {
        if (!extension_loaded('zip')) {
            $this->markTestSkipped('Расширение zip недоступно');
        }

        $content = makeZipContent([
            'a.txt' => 'hello',
            'b.txt' => 'world',
        ]);

        $archive = new ArchiveResponse($content, 'zip');
        $entries = $archive->list();

        expect($entries)->toHaveCount(2)
            ->and($archive->has('a.txt'))->toBeTrue();

        $entry = $archive->get('a.txt');
        expect($entry)->toBeInstanceOf(ArchiveEntry::class)
            ->and($entry?->contents())->toBe('hello');

        $path = tempnam(sys_get_temp_dir(), 'apisutra-zip-');
        if ($path === false) {
            $path = sys_get_temp_dir() . '/apisutra-zip-' . uniqid();
        }

        $entry?->saveTo($path);
        expect(file_get_contents($path) ?: '')->toBe('hello');
        @unlink($path);
    });
});

/**
 * @param array<string, string> $files
 */
function makeZipContent(array $files): string
{
    $temp = tempnam(sys_get_temp_dir(), 'apisutra-zip-');
    if ($temp === false) {
        $temp = sys_get_temp_dir() . '/apisutra-zip-' . uniqid();
    }

    $zip = new ZipArchive();
    $zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();

    $data = file_get_contents($temp) ?: '';
    @unlink($temp);

    return $data;
}
