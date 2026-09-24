<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Files\TemporaryFileStream;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Files\ControlledStream;
use ApiSutra\Tests\Stubs\Auth\RefreshingAuthenticator;
use ApiSutra\Tests\Stubs\Requests\RefreshTokenRequest;
use ApiSutra\Tests\Stubs\Requests\DownloadCacheRequest;
use ApiSutra\Tests\Stubs\ProviderB\Requests\ProviderBDownloadRequest;
use ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\TestClientFactory;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Transport\RecordingTransport;
use ApiSutra\VO\Files\FileInput;
use ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Utils;
use Psr\Log\AbstractLogger;
use ApiSutra\Tests\Stubs\Tracing\MemoryLogger;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir() . '/apisutra-safe-' . bin2hex(random_bytes(6));
    mkdir($this->directory, 0700);
    $this->transport = new MockTransport();
    $this->transport->fake([ProviderBDownloadRequest::class => MockResponse::make('payload')]);
    $this->client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $this->transport);
    $this->request = (new ProviderBDownloadRequest('one'))->setClient($this->client);
});

afterEach(function (): void {
    foreach (glob($this->directory . '/{*,.*}', GLOB_BRACE) ?: [] as $path) {
        if (is_file($path) || is_link($path)) {
            unlink($path);
        }
    }
    rmdir($this->directory);
});

it('защищает существующий файл и разрешает только явную замену', function (): void {
    $path = $this->directory . '/file';
    file_put_contents($path, 'old');
    $failed = $this->request->withDownloadTo($path)->send()->raw();
    expect($failed->errors->first()?->code->value)->toBe('configuration_error')
        ->and(file_get_contents($path))->toBe('old')
        ->and($this->transport->getRecorded())->toHaveCount(0);
    $result = $this->request->withDownloadTo($path, overwrite: true)->send()->raw();
    expect($result->isSuccess())->toBeTrue()
        ->and(file_get_contents($path))->toBe('payload')
        ->and($result->data->content())->toBe('payload');
    $result->data->close();
    expect(file_get_contents($path))->toBe('payload');
});

it('не затирает файл появившийся между HTTP и публикацией', function (): void {
    $path = $this->directory . '/file';
    $this->client->hooks()->on(Hook::AfterHydrate, new class ($path) implements HookInterface {
        public function __construct(private string $path)
        {
        }
        public function handle(PipelineContext $context): ?array
        {
            file_put_contents($this->path, 'racing-writer');
            return null;
        }
    });
    $result = $this->request->withDownloadTo($path)->send()->raw();
    expect($result->isFailed())->toBeTrue()
        ->and(file_get_contents($path))->toBe('racing-writer');
});

it('не публикует файл при ошибке hook', function (): void {
    $path = $this->directory . '/file';
    file_put_contents($path, 'old');
    $this->client->hooks()->on(Hook::AfterHydrate, new class implements HookInterface {
        public function handle(PipelineContext $context): ?array
        {
            throw new RuntimeException('Тестовая ошибка hook');
        }
    });
    $result = $this->request->withDownloadTo($path, overwrite: true)->send()->raw();
    expect($result->isFailed())->toBeTrue()->and(file_get_contents($path))->toBe('old');
});

it('отклоняет недопустимое назначение до HTTP', function (string $kind): void {
    $path = match ($kind) {
        'empty' => '',
        'wrapper' => 'php://temp',
        'directory' => $this->directory,
        'missing' => $this->directory . '/missing/file',
        'symlink' => $this->directory . '/link',
    };
    if ($kind === 'symlink') {
        symlink($this->directory . '/missing', $path);
    }
    $result = $this->request->withDownloadTo($path)->send()->raw();
    expect($result->errors->first()?->code->value)->toBe('configuration_error')
        ->and($this->transport->getRecorded())->toHaveCount(0);
})->with(['empty', 'wrapper', 'directory', 'missing', 'symlink']);

it('обрабатывает короткие записи не закрывая приёмник', function (): void {
    $sink = new ControlledStream(Utils::streamFor(''));
    $result = $this->request->withDownloadTo($sink)->send()->raw();
    expect($result->isSuccess())->toBeTrue()->and((string) $sink)->toBe('payload')
        ->and($sink->closed)->toBeFalse()->and($sink->written)->toBe(7);
});

it('возвращает частичную запись без ложного успеха', function (): void {
    $sink = new ControlledStream(Utils::streamFor(''), failAfter: 2);
    $result = $this->request->withDownloadTo($sink)->send()->raw();
    expect($result->errors->first()?->code->value)->toBe('file_transfer_error')
        ->and($result->errors->first()->context['bytesWritten'])->toBe(2)
        ->and($result->errors->first()->context['partial'])->toBeTrue()
        ->and($result->response->status)->toBe(200)
        ->and((string) $sink)->toBe('pa')->and($sink->closed)->toBeFalse()
        ->and($this->transport->getRecorded())->toHaveCount(1);
});

it('поддерживает non-seekable приёмник', function (): void {
    $inner = Utils::streamFor('');
    $sink = new NoSeekStream($inner);
    $result = $this->request->withDownloadTo($sink)->send()->raw();
    expect($result->isSuccess())->toBeTrue()->and((string) $inner)->toBe('payload');
});

it('очищает runtime цель и не применяет её к обычному запросу', function (): void {
    $path = $this->directory . '/file';
    $result = $this->request->withDownloadTo($path)->withoutDownloadTo()->send()->raw();
    expect($result->isSuccess())->toBeTrue()->and(file_exists($path))->toBeFalse();
    $result = (new CacheProbeRequest())->setClient($this->client)->withDownloadTo($path)->send()->raw();
    expect($result->errors->first()?->code->value)->toBe('configuration_error');
    $original = RequestOptions::empty();
    expect($original->withDownloadTo($path)->withHeader('X-Test', 'one')->getDownloadTarget()->target)->toBe($path)
        ->and($original->getDownloadTarget())->toBeNull();
});

it('отказывается неявно читать JSON из потокового ответа', function (): void {
    $result = $this->request->send()->raw();
    expect(fn () => $result->response->json())->toThrow(ConfigurationException::class)
        ->and(fn () => $result->response->jsonStrict())->toThrow(ConfigurationException::class)
        ->and($result->response->stream->tell())->toBe(0);
});

it('ограничивает чтение сообщения ошибки и сохраняет raw поток', function (string $body, string $expected): void {
    $this->transport->fake([ProviderBDownloadRequest::class => MockResponse::make($body, 500)]);
    $result = $this->request->send()->raw();
    expect($result->response->errorMessage())->toBe($expected)
        ->and($result->response->stream->tell())->toBe(0)
        ->and($result->response->stream->getSize())->toBe(strlen($body));
})->with([['{"message":"fixture failure"}', 'fixture failure'], [str_repeat('x', 70000), 'HTTP 500']]);

it('разделяет владение временным файлом и сохраняет файл после saveTo', function (): void {
    $result = $this->request->send()->raw();
    $file = $result->data;
    $raw = $result->response;
    unset($result);
    $path = $this->directory . '/file';
    file_put_contents($path, 'old');
    $file->saveTo($path);
    expect($raw->stream->isReadable())->toBeTrue()->and(file_get_contents($path))->toBe('payload');
    $file->close();
    expect($raw->stream->isReadable())->toBeFalse()->and(file_exists($path))->toBeTrue();
});

it('удаляет внутренний файл после последнего владельца', function (): void {
    $stream = new TemporaryFileStream($this->directory);
    $other = $stream;
    expect(glob($this->directory . '/.apisutra-*'))->toHaveCount(1);
    unset($stream);
    expect(glob($this->directory . '/.apisutra-*'))->toHaveCount(1);
    unset($other);
    expect(glob($this->directory . '/.apisutra-*'))->toHaveCount(0);
});

it('закрывает только собственные FileInput и сохраняет нулевой размер', function (): void {
    $path = $this->directory . '/empty';
    touch($path);
    $owned = FileInput::fromPath($path);
    $copy = $owned->withMimeType('application/octet-stream');
    expect($owned->size)->toBe(0);
    $copy->close();
    expect($owned->stream->isReadable())->toBeFalse();
    $source = Utils::streamFor('fixture');
    FileInput::fromStream($source, 'fixture')->close();
    expect($source->isReadable())->toBeTrue();
});

it('записывает метаданные файлов без фиктивного playback', function (): void {
    $recording = new RecordingTransport($this->transport, $this->directory);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $recording);
    $result = (new ProviderBDownloadRequest('one'))->setClient($client)->send()->raw();
    expect($result->data->content())->toBe('payload');
    $record = json_decode(file_get_contents(glob($this->directory . '/*.json')[0]), true);
    expect($record['response']['bodyOmitted'])->toBeTrue()->and($record['response']['body'])->toBeNull()
        ->and(fn () => (new MockTransport())->loadFixtures($this->directory))->toThrow(ConfigurationException::class);
});

it('открывает ручную файловую fixture заново на каждый вызов', function (): void {
    $path = $this->directory . '/fixture';
    file_put_contents($path, 'fixture');
    $this->transport->fake([ProviderBDownloadRequest::class => MockResponse::file($path)]);
    $first = $this->request->send()->raw();
    $first->data->close();
    $second = $this->request->send()->raw();
    expect($second->data->content())->toBe('fixture')->and(file_exists($path))->toBeTrue();
});

it('сохраняет timeout и число записанных байтов при исчерпании бюджета', function (): void {
    $clock = new VirtualClock();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(totalTimeoutMs: 5)), $this->transport, $clock, $clock);
    $sink = new ControlledStream(Utils::streamFor(''), onWrite: fn () => $clock->advance(5));
    $result = (new ProviderBDownloadRequest('one'))->setClient($client)->withDownloadTo($sink)->send()->raw();
    expect($result->errors->first()?->code->value)->toBe('timeout')
        ->and($result->errors->first()->context['bytesWritten'])->toBe(2)
        ->and($result->errors->first()->context['partial'])->toBeTrue()
        ->and($result->response->status)->toBe(200)
        ->and($this->transport->getRecorded())->toHaveCount(1);
});

it('сохраняет файл и результат при отказе logger на любой стадии', function (string $failOn): void {
    $logger = new MemoryLogger($failOn);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', logger: $logger), $this->transport);
    $path = $this->directory . '/file';
    $result = (new ProviderBDownloadRequest('one'))->setClient($client)->withDownloadTo($path)->send()->raw();
    expect($result->isSuccess())->toBeTrue()->and(file_get_contents($path))->toBe('payload')
        ->and($logger->records)->not->toBeEmpty()->and($this->transport->getRecorded())->toHaveCount(1);
})->with(['Request started', 'Request completed', '*']);

it('не передаёт sink запросу обновления токена', function (): void {
    RefreshingAuthenticator::reset();
    $this->transport->fake([
        ProviderBDownloadRequest::class => MockResponse::sequence([
            MockResponse::make('unauthorized', 401), MockResponse::make('payload'),
        ]),
        RefreshTokenRequest::class => MockResponse::make(['token' => 'fresh']),
    ]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', auth: new RefreshingAuthenticator()), $this->transport);
    $sink = Utils::streamFor('');
    $result = (new ProviderBDownloadRequest('one'))->setClient($client)->withDownloadTo($sink)->send()->raw();
    expect($result->isSuccess())->toBeTrue()->and((string) $sink)->toBe('payload')
        ->and(RefreshingAuthenticator::$refreshCalls)->toBe(1);
    $refresh = array_values(array_filter($this->transport->getRecorded(), static fn ($request): bool => $request->meta['requestClass'] === RefreshTokenRequest::class))[0];
    expect($refresh->fileTransfer)->toBeNull();
});

it('отклоняет явный кеш без store и позволяет withoutCache снять атрибут', function (): void {
    $this->transport->fake([DownloadCacheRequest::class => MockResponse::make('payload')]);
    $request = (new DownloadCacheRequest())->setClient($this->client);
    $failed = $request->send()->raw();
    expect($failed->errors->first()?->code->value)->toBe('configuration_error')
        ->and($this->transport->getRecorded())->toHaveCount(0);
    expect($request->withoutCache()->send()->dataOrFail()->content())->toBe('payload');
});

it('сохраняет читаемый FileResponse при write-only приёмнике', function (): void {
    $path = $this->directory . '/sink';
    $sink = Utils::streamFor(fopen($path, 'wb'));
    try {
        expect($sink->isReadable())->toBeFalse();
        $result = $this->request->withDownloadTo($sink)->send()->raw();
        expect($result->data->content())->toBe('payload')
            ->and(file_get_contents($path))->toBe('payload')->and($sink->isWritable())->toBeTrue();
    } finally {
        $sink->close();
    }
});

it('возвращает ошибку публикации без замены старого файла', function (): void {
    $path = $this->directory . '/file';
    file_put_contents($path, 'old');
    $this->client->hooks()->on(Hook::AfterHydrate, new class implements HookInterface {
        public function handle(PipelineContext $context): ?array
        {
            // Моделируем исчезновение staging-файла перед атомарной публикацией.
            unlink($context->response->stream->getMetadata('uri'));
            return null;
        }
    });
    $result = $this->request->withDownloadTo($path, overwrite: true)->send()->raw();
    expect($result->errors->first()?->code->value)->toBe('file_transfer_error')
        ->and(file_get_contents($path))->toBe('old');
});
