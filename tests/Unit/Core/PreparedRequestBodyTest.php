<?php

declare(strict_types=1);

use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Tests\Stubs\Files\ControlledStream;
use ApiSutra\Tests\Stubs\Retry\ConsumingHttpClient;
use ApiSutra\Tests\Support\LocalUrlServer;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\VO\Files\FileTransferOptions;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\TransportOptions;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

it('отправляет выбранное тело вместо прежнего потока', function (string $operation, string $expected): void {
    $original = new PreparedRequest(HttpMethod::POST, 'https://fixture.test', stream: Utils::streamFor('old-file'));
    $changed = match ($operation) {
        'with' => $original->with(body: 'new-string'),
        'empty' => $original->with(body: ''),
        'clear' => $original->withoutBody(),
    };
    $http = new ConsumingHttpClient([new Response(204)]);
    $factory = new HttpFactory();
    (new HttpTransport($http, $factory, $factory))->send($changed);
    expect($http->bodies)->toBe([$expected])
        ->and($changed->stream)->toBeNull()
        ->and($original->stream->tell())->toBe(0);
})->with([['with', 'new-string'], ['empty', ''], ['clear', '']]);

it('отвергает неоднозначный источник тела при создании и копировании', function (bool $copy, string $body): void {
    $stream = Utils::streamFor('file');
    expect(fn () => $copy
        ? (new PreparedRequest(HttpMethod::POST, 'https://fixture.test'))->with(body: $body, stream: $stream)
        : new PreparedRequest(HttpMethod::POST, 'https://fixture.test', body: $body, stream: $stream)
    )->toThrow(ConfigurationException::class);
    expect($stream->tell())->toBe(0);
})->with([false, true])->with(['text', '']);

it('убирает унаследованный framing и старый снимок body при замене', function (): void {
    $original = new PreparedRequest(
        HttpMethod::POST, 'https://fixture.test',
        headers: ['cOnTeNt-LeNgTh' => '99', 'TRANSFER-ENCODING' => 'chunked', 'Content-Type' => 'text/plain'],
        body: 'old', meta: ['body' => ['old' => true], 'bodyIsRoot' => true, 'files' => ['fixture']],
    );
    $changed = $original->with(body: 'new');
    expect($changed->headers)->toBe(['Content-Type' => 'text/plain'])
        ->and($changed->meta)->toBe(['files' => ['fixture']])
        ->and($original->meta['body'])->toBe(['old' => true]);
});

it('сохраняет immutable переходы без чтения и сброса остальных опций', function (string $from, string $to): void {
    $stream = new ControlledStream(Utils::streamFor('prefix-file'));
    $stream->seek(7);
    $options = new TransportOptions(timeoutMs: 900);
    $transfer = new FileTransferOptions(upload: true);
    $original = new PreparedRequest(
        HttpMethod::POST, 'https://fixture.test', headers: ['Content-Type' => 'application/octet-stream'],
        body: match ($from) { 'string' => 'old', 'empty' => '', default => null },
        stream: $from === 'stream' ? $stream : null,
        meta: ['files' => ['fixture']], transportOptions: $options, fileTransfer: $transfer,
    );
    $changed = match ($to) {
        'string' => $original->withBody('new'),
        'empty' => $original->withBody(''),
        'stream' => $original->withStream($stream),
        'none' => $original->withoutBody()->withoutBody(),
    };
    expect($changed)->not->toBe($original)
        ->and($changed->body)->toBe(match ($to) { 'string' => 'new', 'empty' => '', default => null })
        ->and($changed->stream)->toBe($to === 'stream' ? $stream : null)
        ->and($changed->transportOptions)->toBe($options)
        ->and($changed->fileTransfer)->toBe($transfer)
        ->and($changed->meta)->toBe($original->meta)
        ->and($changed->headers)->toBe($original->headers)
        ->and($stream->tell())->toBe(7)
        ->and($stream->reads)->toBe(0)
        ->and($stream->closed)->toBeFalse();
    $legacy = $original->with(body: null, stream: null)->withHeader('X-Fixture', 'yes');
    expect($legacy->body)->toBe($original->body)->and($legacy->stream)->toBe($original->stream);
})->with(['none', 'string', 'empty', 'stream'])->with(['none', 'string', 'empty', 'stream']);

it('проверяет явный framing до HTTP без чтения тела', function (array $headers, string $kind): void {
    $stream = Utils::streamFor('file');
    $http = new ConsumingHttpClient([]);
    $factory = new HttpFactory();
    $original = new PreparedRequest(HttpMethod::POST, 'https://fixture.test', body: 'old');
    $changed = $kind === 'stream'
        ? $original->with(stream: $stream, headers: $headers)
        : $original->with(body: 'new', headers: $headers);
    expect($changed->headers)->toBe($headers);
    expect(fn () => (new HttpTransport($http, $factory, $factory))->send($changed))
        ->toThrow(ConfigurationException::class);
    expect($http->bodies)->toBe([])->and($stream->tell())->toBe(0);
})->with([
    [['content-length' => '99'], 'string'],
    [['Content-Length' => '3', 'Transfer-Encoding' => 'chunked'], 'string'],
    [['Content-Length' => '3', 'content-length' => '4'], 'string'],
    [['Content-Length' => '-1'], 'string'],
    [['Content-Length' => '3.0'], 'string'],
    [['Content-Length' => '99999999999999999999999999'], 'stream'],
    [['Content-Length' => '3'], 'stream'],
]);

it('принимает явную правильную длину и неизвестный размер потока', function (bool $unknown): void {
    $stream = $unknown
        ? new class implements StreamInterface {
            use StreamDecoratorTrait;
            private StreamInterface $stream;

            public function __construct()
            {
                $this->stream = Utils::streamFor('new');
            }

            public function getSize(): ?int
            {
                return null;
            }
        }
        : Utils::streamFor('new');
    $original = new PreparedRequest(HttpMethod::POST, 'https://fixture.test', body: 'old');
    $changed = $original->with(stream: $stream, headers: ['content-length' => '3', 'Content-Type' => 'text/plain']);
    $http = new ConsumingHttpClient([new Response(204)]);
    $factory = new HttpFactory();
    (new HttpTransport($http, $factory, $factory))->send($changed);
    expect($http->bodies)->toBe(['new'])
        ->and($http->requests[0]->getHeaderLine('Content-Length'))->toBe('3');
})->with([false, true]);

it('штатный Guzzle отправляет новые байты и длину локальному HTTP', function (bool $clear): void {
    $server = new LocalUrlServer();
    try {
        $original = new PreparedRequest(
            HttpMethod::POST, $server->url . '/echo',
            headers: ['cOnTeNt-LeNgTh' => '8', 'TRANSFER-ENCODING' => 'chunked', 'Content-Type' => 'multipart/form-data; boundary=old'],
            stream: Utils::streamFor('old-file'),
        );
        $changed = $clear ? $original->withoutBody() : $original->withBody('{"new":true}')->withHeader('Content-Type', 'application/json');
        $data = HttpTransport::createDefault()->send($changed)->jsonStrict();
        $expected = $clear ? '' : '{"new":true}';
        expect($data['body'])->toBe($expected)
            ->and($data['headers']['content-length'])->toBe((string) strlen($expected))
            ->and($data['headers'])->not->toHaveKey('transfer-encoding')
            ->and($data['headers']['content-type'])->toBe($clear ? 'multipart/form-data; boundary=old' : 'application/json')
            ->and($original->stream->tell())->toBe(0);
    } finally {
        $server->close();
    }
})->with([false, true]);
