<?php

declare(strict_types=1);

use ApiSutra\Core\AbstractRequest;
use ApiSutra\Request\RequestExecution;
use ApiSutra\Result\ResultHandle;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Tests\Stubs\ProviderB\Requests\ProviderBDownloadRequest;
use ApiSutra\Tests\Stubs\Requests\BinaryUploadRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\LocalFileServer;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\Transport\GuzzleHttpClient;
use ApiSutra\VO\Files\FileInput;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\NoSeekStream;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Tests\Stubs\Files\ControlledStream;

foreach ([
    'sync' => static fn (AbstractRequest|RequestExecution $request): ResultHandle => $request->send(),
    'async' => static fn (AbstractRequest|RequestExecution $request): ResultHandle => $request->sendAsync()->wait(),
] as $mode => $send) {
    describe($mode, function () use ($send): void {
        beforeEach(function () use ($send): void {
            $this->send = $send;
        });
        it('передаёт binary с ненулевой позиции через реальный HTTP', function (int $size): void {
            $server = new LocalFileServer();
            $client = new TestClient(new ClientConfig(baseUrl: $server->url), HttpTransport::createDefault());
            $source = Utils::streamFor('prefix:' . str_repeat('Z', $size));
            $source->seek(7);
            $data = ($this->send)((new BinaryUploadRequest(FileInput::fromStream($source, 'fixture.bin')))
                ->setClient($client)->withUrl($server->url . '/upload?sig=fixture&'))->dataOrFail();
            expect($data['bytes'])->toBe($size)
                ->and($data['sha256'])->toBe(hash('sha256', str_repeat('Z', $size)))
                ->and($source->isReadable())->toBeTrue();
        })->with([0, 12, 1100000]);

        it('сохраняет поток download с известной и неизвестной длиной', function (string $endpoint, int $size): void {
            $server = new LocalFileServer();
            $client = new TestClient(new ClientConfig(baseUrl: $server->url), HttpTransport::createDefault());
            $result = ($this->send)((new ProviderBDownloadRequest('one'))->setClient($client)
                ->withUrl($server->url . $endpoint . '?size=' . $size))->raw();
            expect($result->response->body)->toBeNull()
                ->and($result->data->size())->toBe($size)
                ->and($result->data->stream()->tell())->toBe(0)
                ->and($result->data->content())->toBe(str_repeat('Z', $size));
        })->with([['/download', 0], ['/download', 80000], ['/unknown', 80000], ['/gzip', 80000]]);

        it('повторяет download без смешивания попыток', function (): void {
            $server = new LocalFileServer();
            $client = new TestClient(new ClientConfig(baseUrl: $server->url, retry: new RetryConfig(attempts: 2, baseDelay: 0, jitter: false)), HttpTransport::createDefault());
            $sink = Utils::streamFor('');
            $result = ($this->send)((new ProviderBDownloadRequest('one'))->setClient($client)
                ->withUrl($server->url . '/retry-download?size=128')->withDownloadTo($sink))->raw();
            expect($result->response->status)->toBe(200)
                ->and($result->data->content())->toBe(str_repeat('Z', 128))
                ->and((string) $sink)->toBe(str_repeat('Z', 128));
        });

        it('не заменяет существующий файл усечённым HTTP ответом', function (): void {
            $server = new LocalFileServer();
            $client = new TestClient(new ClientConfig(baseUrl: $server->url), HttpTransport::createDefault());
            $path = tempnam(sys_get_temp_dir(), 'apisutra-test-');
            file_put_contents($path, 'old');
            try {
                $result = ($this->send)((new ProviderBDownloadRequest('one'))->setClient($client)
                    ->withUrl($server->url . '/truncated?size=1024')->withDownloadTo($path, overwrite: true))->raw();
                expect($result->errors->first())->not->toBeNull()
                    ->and(file_get_contents($path))->toBe('old');
            } finally {
                unlink($path);
            }
        });

        it('отправляет non-seekable binary только один раз', function (): void {
            $server = new LocalFileServer();
            $config = new ClientConfig(baseUrl: $server->url, retry: new RetryConfig(attempts: 2, safeMethods: [HttpMethod::POST], baseDelay: 0, jitter: false));
            $client = new TestClient($config, HttpTransport::createDefault());
            $source = new NoSeekStream(Utils::streamFor('fixture'));
            $result = ($this->send)((new BinaryUploadRequest(FileInput::fromStream($source, 'fixture.bin')))->setClient($client)
                ->withUrl($server->url . '/retry-upload'))->raw();
            expect($result->response->status)->toBe(503)
                ->and($result->response->json()['attempt'])->toBe(1)
                ->and($result->errors->first()->context['retryRefusalReason'])->toBe('body_not_replayable');
        });

        it('не повторяет локальную ошибку чтения даже при retryExceptions Throwable', function (): void {
            $server = new LocalFileServer();
            $config = new ClientConfig(baseUrl: $server->url, retry: new RetryConfig(attempts: 2, safeMethods: [HttpMethod::POST], retryExceptions: [Throwable::class], baseDelay: 0, jitter: false));
            $client = new TestClient($config, HttpTransport::createDefault());
            $source = new ControlledStream(Utils::streamFor('fixture'), readFails: true);
            $result = ($this->send)((new BinaryUploadRequest(FileInput::fromStream($source, 'fixture.bin')))->setClient($client)
                ->withUrl($server->url . '/upload'))->raw();
            expect($result->errors->first()?->code->value)->toBe('file_transfer_error')
                ->and($source->reads)->toBe(1)->and($source->closed)->toBeFalse();
        });

        it('исключает скрытые body и sink defaults при файловом вызове своего origin', function (): void {
            $server = new LocalFileServer();
            $path = sys_get_temp_dir() . '/apisutra-hidden-sink-' . bin2hex(random_bytes(6));
            $factory = new HttpFactory();
            $transport = new HttpTransport(new GuzzleHttpClient(['body' => 'wrong', 'json' => ['wrong' => true], 'sink' => $path]), $factory, $factory);
            $client = new TestClient(new ClientConfig(baseUrl: $server->url), $transport);
            try {
                $data = ($this->send)((new BinaryUploadRequest(FileInput::fromContent('fixture', 'fixture.bin')))->setClient($client))->dataOrFail();
                expect($data['bytes'])->toBe(7)->and($data['sha256'])->toBe(hash('sha256', 'fixture'))
                    ->and(file_exists($path))->toBeFalse();
            } finally {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        });
    });
}
