<?php

declare(strict_types=1);

use ApiSutra\Auth\ApiKeyAuthenticator;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Core\PsrNetworkFailure;
use ApiSutra\Tests\Stubs\ProviderB\Requests\ProviderBDownloadRequest;
use ApiSutra\Tests\Stubs\Requests\BinaryUploadRequest;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\Retry\ConsumingHttpClient;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\SpyCache;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Files\FileInput;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

it('debug показывает отправленное тело после BeforeSend', function (): void {
    $http = new ConsumingHttpClient([new Response(200, ['Content-Type' => 'application/json'], '{"id":1,"name":"Example"}')]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', debug: true), new HttpTransport($http, $factory, $factory));
    $client->hooks()->on(Hook::BeforeSend, new class implements HookInterface {
        public function handle(PipelineContext $context): ?array
        {
            $context->preparedRequest = $context->preparedRequest->with(body: '{"token":"fixture-secret","new":true}')
                ->withHeader('Content-Type', 'application/json');
            return null;
        }
    });
    $result = (new SimpleGetRequest('q'))->setClient($client)->send()->raw();
    expect($result->isSuccess())->toBeTrue()
        ->and($result->requestDebug(false)['bodyRaw'])->toBe($http->bodies[0])
        ->and($result->requestDebugJson())->not->toContain('fixture-secret');
});

it('итоговый debug использует запрос ответа, а при сетевом сбое актуальный контекст', function (string $outcome): void {
    $response = $outcome === 'network'
        ? new PsrNetworkFailure(new Request('GET', 'https://fixture.test'))
        : new Response($outcome === 'failure' ? 503 : 200, ['Content-Type' => 'application/json'], '{"id":1,"name":"Example"}');
    $http = new ConsumingHttpClient([$response]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', debug: true), new HttpTransport($http, $factory, $factory));
    $client->hooks()->on(Hook::BeforeSend, new class implements HookInterface {
        public function handle(PipelineContext $context): ?array
        {
            $context->preparedRequest = $context->preparedRequest
                ->with(meta: [...$context->preparedRequest->meta, 'body' => ['old' => true], 'bodyIsRoot' => true])
                ->withBody('{"token":"fixture-secret","new":true}')
                ->withHeader('Content-Type', 'application/json');
            return null;
        }
    });
    $client->hooks()->on(Hook::AfterResponse, new class implements HookInterface {
        public function handle(PipelineContext $context): ?array
        {
            $context->preparedRequest = $context->preparedRequest->withBody('never sent');
            return null;
        }
    });
    $result = (new SimpleGetRequest('q'))->setClient($client)->send()->raw();
    expect($result->isSuccess())->toBe($outcome === 'success')
        ->and($result->requestDebug(false)['bodyRaw'])->toBe($http->bodies[0])
        ->and($result->requestDebug(false)['body'])->toBeNull()
        ->and($result->requestDebugJson())->not->toContain('fixture-secret', 'never sent');
})->with(['success', 'failure', 'network']);

it('некорректный framing после hook возвращает configuration_error без HTTP', function (): void {
    $http = new ConsumingHttpClient([]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', debug: true), new HttpTransport($http, $factory, $factory));
    $client->hooks()->on(Hook::BeforeSend, new class implements HookInterface {
        public function handle(PipelineContext $context): ?array
        {
            $context->preparedRequest = $context->preparedRequest->with(body: 'new', headers: ['Content-Length' => '99']);
            return null;
        }
    });
    $result = (new SimpleGetRequest('q'))->setClient($client)->send()->raw();
    expect($result->errors->first()->code->value)->toBe('configuration_error')
        ->and($http->bodies)->toBe([])
        ->and($result->requestDebug(false)['bodyRaw'])->toBe('new');
});

it('неоднозначный выбор внутри hook сохраняет классификацию ConfigurationException и throwOnErrors', function (bool $throws): void {
    $http = new ConsumingHttpClient([]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', throwOnErrors: $throws), new HttpTransport($http, $factory, $factory));
    $client->hooks()->on(Hook::BeforeSend, new class implements HookInterface {
        public function handle(PipelineContext $context): ?array
        {
            $context->preparedRequest = $context->preparedRequest->with(body: 'new', stream: Utils::streamFor('file'));
            return null;
        }
    });
    $send = fn () => (new SimpleGetRequest('q'))->setClient($client)->send()->raw();
    if ($throws) {
        expect($send)->toThrow(ConfigurationException::class);
    } else {
        expect($send()->errors->first()->code->value)->toBe('configuration_error');
    }
    expect($http->bodies)->toBe([]);
})->with([false, true]);

it('recorder после hook пишет отправленную строку с redaction', function (): void {
    $directory = sys_get_temp_dir() . '/apisutra-body-record-' . bin2hex(random_bytes(8));
    $http = new ConsumingHttpClient([new Response(200, ['Content-Type' => 'application/json'], '{"id":1,"name":"Example"}')]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), new HttpTransport($http, $factory, $factory));
    $client->hooks()->on(Hook::BeforeSend, new class implements HookInterface {
        public function handle(PipelineContext $context): ?array
        {
            $context->preparedRequest = $context->preparedRequest->withBody('{"new":true,"token":"fixture-secret"}')
                ->withHeader('Content-Type', 'application/json');
            return null;
        }
    });
    try {
        $client->record($directory);
        expect((new SimpleGetRequest('q'))->setClient($client)->send()->raw()->isSuccess())->toBeTrue();
        $files = glob($directory . '/*.json');
        expect($files)->toHaveCount(1);
        $record = file_get_contents($files[0]);
        expect($record)->toContain('new')->not->toContain('fixture-secret');
        $data = json_decode($record, true, flags: JSON_THROW_ON_ERROR);
        expect($data['request']['body']['new'])->toBeTrue();
    } finally {
        foreach (glob($directory . '/*.json') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});

it('поток добавленный hook требует capability даже у пользовательского транспорта', function (): void {
    $transport = new class implements TransportInterface {
        public int $calls = 0;
        public function send(PreparedRequest $request): ProviderResponse
        {
            $this->calls++;
            throw new RuntimeException('HTTP не должен вызываться');
        }
        public function sendAsync(PreparedRequest $request): PromiseInterface
        {
            throw new RuntimeException('HTTP не должен вызываться');
        }
    };
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', timeout: 0, connectTimeout: 0), $transport);
    $client->hooks()->on(Hook::BeforeSend, new class implements HookInterface {
        public function handle(PipelineContext $context): ?array
        {
            $context->preparedRequest = $context->preparedRequest->withStream(Utils::streamFor('new'));
            return null;
        }
    });
    $result = (new SimpleGetRequest('q'))->setClient($client)->send()->raw();
    expect($result->errors->first()->code->value)->toBe('configuration_error')
        ->and($result->errors->first()->message)->toContain('FileStreamingInterface')
        ->and($transport->calls)->toBe(0);
});

it('очистка upload сохраняет запрет кеша, auth и общий бюджет', function (): void {
    $cache = new SpyCache();
    $http = new ConsumingHttpClient([new Response(204), new Response(204)]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test',
        cacheConfig: new CacheConfig(store: $cache),
        auth: new ApiKeyAuthenticator('fixture-secret'),
        retry: new RetryConfig(totalTimeoutMs: 3000),
    ), new HttpTransport($http, $factory, $factory));
    $client->hooks()->on(Hook::BeforeSend, new class implements HookInterface {
        public function handle(PipelineContext $context): ?array
        {
            $context->preparedRequest = $context->preparedRequest->withoutBody();
            return null;
        }
    });
    $request = (new BinaryUploadRequest(FileInput::fromContent('old-file', 'file')))->setClient($client);
    expect($request->send()->raw()->isSuccess())->toBeTrue()
        ->and($request->send()->raw()->isSuccess())->toBeTrue()
        ->and($http->bodies)->toBe(['', ''])
        ->and($http->requests[0]->getHeaderLine('X-Api-Key'))->toBe('fixture-secret')
        ->and($http->options[0]->budget)->not->toBeNull()
        ->and($http->options[0]->fileTransfer->upload)->toBeTrue()
        ->and($cache->lastGetKey)->toBeNull()
        ->and($cache->lastSetKey)->toBeNull();
});

it('очистка тела сохраняет download target и защиту подписанного URL', function (bool $changeUrl): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('payload')]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', debug: true), $transport);
    $client->hooks()->on(Hook::BeforeSend, new class ($changeUrl) implements HookInterface {
        public function __construct(private bool $changeUrl)
        {
        }

        public function handle(PipelineContext $context): ?array
        {
            $context->preparedRequest = $context->preparedRequest->withBody('old')->withoutBody();
            if ($this->changeUrl) {
                $context->preparedRequest = $context->preparedRequest->with(url: 'https://evil.test');
            }
            return null;
        }
    });
    $sink = Utils::streamFor('');
    $result = (new ProviderBDownloadRequest('one'))->setClient($client)
        ->withUrl('https://download.test/fixture-path-secret?sig=fixture-query-secret')->withDownloadTo($sink)->send()->raw();
    if ($changeUrl) {
        expect($result->errors->first()->code->value)->toBe('configuration_error')
            ->and($transport->getRecorded())->toHaveCount(0)->and((string) $sink)->toBe('');
    } else {
        expect($result->isSuccess())->toBeTrue()
            ->and((string) $sink)->toBe('payload')
            ->and($result->response->request->body)->toBeNull()
            ->and($result->response->request->fileTransfer->target->target)->toBe($sink)
            ->and($result->requestDebugJson())->not->toContain('fixture-path-secret', 'fixture-query-secret');
    }
    expect($sink->isWritable())->toBeTrue();
})->with([false, true]);

it('тело выбранное до первой отправки повторяется и debug относится к последней попытке', function (): void {
    $http = new ConsumingHttpClient([
        new Response(503),
        new Response(200, ['Content-Type' => 'application/json'], '{"id":1,"name":"Example"}'),
    ]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', debug: true, retry: new RetryConfig(attempts: 2, baseDelay: 0),
    ), new HttpTransport($http, $factory, $factory));
    $client->hooks()->on(Hook::BeforeSend, new class implements HookInterface {
        public function handle(PipelineContext $context): ?array
        {
            $context->preparedRequest = $context->preparedRequest->withBody('old')->withStream(Utils::streamFor('new-file'));
            return null;
        }
    });
    $client->hooks()->on(Hook::AfterResponse, new class implements HookInterface {
        public function handle(PipelineContext $context): ?array
        {
            $context->preparedRequest = $context->preparedRequest->withHeader('X-Attempt', 'second');
            return null;
        }
    });
    $result = (new SimpleGetRequest('q'))->setClient($client)->send()->raw();
    expect($result->isSuccess())->toBeTrue()
        ->and($http->bodies)->toBe(['new-file', 'new-file'])
        ->and($result->requestDebug(false)['hasStream'])->toBeTrue()
        ->and($result->requestDebug(false)['bodyRaw'])->toBeNull()
        ->and($result->requestDebug(false)['headers']['X-Attempt'])->toBe('second');
});
