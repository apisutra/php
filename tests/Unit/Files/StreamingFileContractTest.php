<?php

declare(strict_types=1);

use ApiSutra\Enums\Http\FileFormat;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Tests\Stubs\Auth\RecordingAuthenticator;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use GuzzleHttp\Promise\PromiseInterface;
use ApiSutra\Serialization\FilePayloadPreparer;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\ProviderB\Requests\ProviderBDownloadRequest;
use ApiSutra\Tests\Support\TestClientFactory;
use ApiSutra\VO\Files\FileInput;
use GuzzleHttp\Psr7\Utils;

it('передаёт binary с текущей позиции без строковой копии', function () {
    $source = Utils::streamFor('prefix:payload');
    $source->seek(7);
    $prepared = (new FilePayloadPreparer())->prepareBodyAndStream(
        FileFormat::Binary,
        [['name' => 'file', 'file' => FileInput::fromStream($source, 'fixture.bin')]],
        [], false, [],
    );
    expect($prepared['body'])->toBeNull()
        ->and($source->tell())->toBe(7)
        ->and((string) $prepared['stream'])->toBe('payload');
    $prepared['stream']->close();
    expect($source->isReadable())->toBeTrue();
});

it('сохраняет raw download в потоке', function () {
    $client = TestClientFactory::make([
        ProviderBDownloadRequest::class => MockResponse::make('fixture-bytes'),
    ]);
    $request = new ProviderBDownloadRequest('one');
    $request->setClient($client);
    $result = $request->send()->raw();
    expect($result->response->body)->toBeNull()
        ->and($result->response->stream)->toBe($result->data->stream())
        ->and($result->data->content())->toBe('fixture-bytes');
});

it('отклоняет явный кеш download до HTTP', function () {
    $client = TestClientFactory::make([
        ProviderBDownloadRequest::class => MockResponse::make('fixture-bytes'),
    ]);
    $request = new ProviderBDownloadRequest('one');
    $request->setClient($client);
    $result = $request->withCache()->send()->raw();
    expect($result->errors->first()?->code->value)->toBe('configuration_error');
});

it('сохраняет успешный download в переданный поток и оставляет его открытым', function () {
    $client = TestClientFactory::make([
        ProviderBDownloadRequest::class => MockResponse::make('fixture-bytes'),
    ]);
    $request = new ProviderBDownloadRequest('one');
    $request->setClient($client);
    $sink = Utils::streamFor('prefix:');
    $sink->seek(7);
    $result = $request->withDownloadTo($sink)->send()->raw();
    expect($result->data->content())->toBe('fixture-bytes')
        ->and($sink->isWritable())->toBeTrue()
        ->and((string) $sink)->toBe('prefix:fixture-bytes');
});

it('отклоняет неподдерживающий транспорт до auth и HTTP', function (): void {
    RecordingAuthenticator::reset();
    $transport = new class implements TransportInterface {
        public int $calls = 0;
        public function send(PreparedRequest $request): ProviderResponse
        {
            $this->calls++;
            throw new RuntimeException('HTTP не должен вызываться');
        }
        public function sendAsync(PreparedRequest $request): PromiseInterface
        {
            $this->calls++;
            throw new RuntimeException('HTTP не должен вызываться');
        }
    };
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', auth: new RecordingAuthenticator()), $transport);
    $result = (new ProviderBDownloadRequest('one'))->setClient($client)->send()->raw();
    expect($result->errors->first()?->code->value)->toBe('configuration_error')
        ->and($transport->calls)->toBe(0)->and(RecordingAuthenticator::$authenticateCalls)->toBe(0);
});
