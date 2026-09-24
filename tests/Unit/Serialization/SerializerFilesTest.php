<?php

declare(strict_types=1);

use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Enums\Http\FileFormat;
use ApiSutra\Serialization\FilePayloadPreparer;
use ApiSutra\Serialization\Serializer;
use ApiSutra\Tests\Stubs\Requests\Base64UploadRequest;
use ApiSutra\Tests\Stubs\Requests\BinaryUploadRequest;
use ApiSutra\Tests\Stubs\Requests\CredentialsRequest;
use ApiSutra\Tests\Stubs\Requests\JsonWithCustomContentTypeRequest;
use ApiSutra\Tests\Stubs\Requests\MultipartUploadRequest;
use ApiSutra\VO\Files\FileInput;
use ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Psr7\MultipartStream;

describe('Serializer файлы', function () {
    it('кодирует опасные символы имени только в multipart заголовке', function (string $filename, string $encoded): void {
        $file = FileInput::fromContent('sample', $filename);
        try {
            $payload = (new FilePayloadPreparer())->prepareBodyAndStream(
                FileFormat::Multipart,
                [['name' => 'document', 'file' => $file]],
                [],
                false,
                [],
            );
            $wire = (string) $payload['stream'];
            expect($wire)->toContain('Content-Disposition: form-data; name="document"; filename="' . $encoded . '"' . "\r\n")
                ->and(substr_count($wire, 'Content-Disposition:'))->toBe(1)
                ->and($wire)->toContain("\r\n\r\nsample\r\n")
                ->and($file->filename)->toBe($filename);
        } finally {
            $file->close();
        }
    })->with([
        'field injection' => ['a"; name="evil.txt', 'a%22; name=%22evil.txt'],
        'header injection' => ["a\r\nX-Injected: yes.txt", 'a%0D%0AX-Injected: yes.txt'],
        'carriage return' => ["a\rb.txt", 'a%0Db.txt'],
        'line feed' => ["a\nb.txt", 'a%0Ab.txt'],
        'unicode and percent' => ['отчёт 100%.txt', 'отчёт 100%.txt'],
        'literal escape' => ['report%22.txt', 'report%22.txt'],
    ]);

    it('сериализует multipart с массивом файлов', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);

        $files = [
            FileInput::fromContent('first', 'first.txt'),
            FileInput::fromContent('second', 'second.txt'),
        ];
        $request = new MultipartUploadRequest($files, 'note');

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);

        expect($prepared->stream)->toBeInstanceOf(MultipartStream::class)
            ->and($prepared->body)->toBeNull()
            ->and($prepared->headers['Content-Type'] ?? null)
            ->toStartWith('multipart/form-data; boundary=')
            ->and($prepared->meta['body']['comment'] ?? null)->toBe('note')
            ->and($prepared->meta['files'])->toHaveCount(2);
    });

    it('сериализует бинарный файл и выставляет content-type', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);

        $file = FileInput::fromContent('PDF', 'doc.pdf')
            ->withMimeType('application/pdf');
        $request = new BinaryUploadRequest($file);

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);

        expect((string) $prepared->stream)->toBe('PDF')
            ->and($prepared->body)->toBeNull()
            ->and($prepared->headers['Content-Type'] ?? null)->toBe('application/pdf')
            ->and($prepared->meta['files'])->toHaveCount(1);
    });

    it('сериализует base64 файл в json тело', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);

        $file = FileInput::fromContent('data', 'file.txt');
        $request = new Base64UploadRequest($file, 'note');

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);
        $payload = json_decode($prepared->body ?? '', true);

        expect($prepared->stream)->toBeNull()
            ->and($payload['file'] ?? null)->toBe(base64_encode('data'))
            ->and($payload['note'] ?? null)->toBe('note')
            ->and($prepared->headers['Content-Type'] ?? null)->toBe('application/json')
            ->and($prepared->meta['files'])->toHaveCount(1);
    });

    it('выставляет Content-Type: application/json для JSON body по умолчанию', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);

        $request = new CredentialsRequest(login: 'user', plain: 'data');

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);

        expect($prepared->body)->not->toBeNull()
            ->and($prepared->headers['Content-Type'] ?? null)->toBe('application/json');
    });

    it('не перезаписывает явно заданный Content-Type', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);

        $request = new JsonWithCustomContentTypeRequest(data: '{"x":1}', contentType: 'application/xml');

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);

        expect($prepared->body)->not->toBeNull()
            ->and($prepared->headers['Content-Type'] ?? null)->toBe('application/xml');
    });
});
