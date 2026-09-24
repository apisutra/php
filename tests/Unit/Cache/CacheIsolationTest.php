<?php

declare(strict_types=1);

use ApiSutra\Auth\ApiKeyAuthenticator;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Enums\Http\QueryArrayFormat;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\AttributeRichRequest;
use ApiSutra\Tests\Stubs\Requests\CachePostRequest;
use ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Auth\RefreshingAuthenticator;
use ApiSutra\Tests\Support\ControllableTimeCache;
use ApiSutra\Tests\Support\StrictCache;
use ApiSutra\Tests\Support\ArrayCache;
use ApiSutra\Tests\Stubs\Requests\RefreshTokenRequest;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Pipeline\PipelineContext;

beforeEach(function (): void {
    $this->store = new StrictCache();
    $this->transport = new MockTransport();
    $this->transport->fake(['*' => MockResponse::sequence([
        MockResponse::success(['value' => 1]),
        MockResponse::success(['value' => 2]),
        MockResponse::success(['value' => 3]),
        MockResponse::success(['value' => 4]),
        MockResponse::success(['value' => 5]),
    ])]);
    $this->config = new ClientConfig(
        baseUrl: 'https://api.test',
        cacheConfig: new CacheConfig(store: $this->store, prefix: 'provider:account-a'),
        environment: Environment::Testing,
    );
    $this->client = new TestClient($this->config, $this->transport);
});

it('изолирует двух клиентов, позволяет общий scope и очищает только своё пространство', function (): void {
    $b = new TestClient($this->config->with(cacheConfig: new CacheConfig(store: $this->store, prefix: 'provider:account-b')), $this->transport);
    $same = new TestClient($this->config, $this->transport);
    $aRequest = (new CacheProbeRequest())->setClient($this->client);
    $bRequest = (new CacheProbeRequest())->setClient($b);
    expect($aRequest->dataOrFail())->toBe(['value' => 1])
        ->and($bRequest->dataOrFail())->toBe(['value' => 2])
        ->and((new CacheProbeRequest())->setClient($same)->dataOrFail())->toBe(['value' => 1]);

    $this->client->clearCache();

    expect($aRequest->dataOrFail())->toBe(['value' => 3])
        ->and($bRequest->dataOrFail())->toBe(['value' => 2])
        ->and($this->transport->getRecorded())->toHaveCount(3);
    foreach ($this->store->keys as $key) {
        expect($key)->toMatch('/^[a-f0-9]{64}$/');
    }
});

it('без prefix кеширует в автоматическом пространстве и безопасно очищает его', function (): void {
    $client = new TestClient($this->config->with(cacheConfig: new CacheConfig(store: $this->store)), $this->transport);
    $request = (new CacheProbeRequest())->setClient($client);
    expect($request->withCache()->dataOrFail())->toBe(['value' => 1])
        ->and($request->withCache()->dataOrFail())->toBe(['value' => 1]);
    $this->store->set('foreign', 'keep');
    $client->clearCache();
    expect($this->store->get('foreign'))->toBe('keep')
        ->and($request->dataOrFail())->toBe(['value' => 2]);
});

it('разделяет runtime пространства и сохраняет исходную цепочку', function (): void {
    $request = (new CacheProbeRequest())->setClient($this->client);
    $b = $request->withCacheScope('provider:account-b');
    expect($request->dataOrFail())->toBe(['value' => 1])
        ->and($b->dataOrFail())->toBe(['value' => 2])
        ->and($request->dataOrFail())->toBe(['value' => 1]);
    $b->clearCache();
    expect($b->dataOrFail())->toBe(['value' => 3])
        ->and($request->dataOrFail())->toBe(['value' => 1]);
    expect(fn () => $request->withCacheScope(' '))->toThrow(ConfigurationException::class);
});

it('различает фактические URI, порядок списков и формат query', function (string $case): void {
    $a = new CacheProbeRequest();
    $b = new CacheProbeRequest();
    $otherClient = $this->client;
    if ($case === 'uri') {
        $a = new CacheProbeRequest('/items?fixed=1');
        $b = new CacheProbeRequest('/items?fixed=2');
    } elseif ($case === 'order') {
        $a = new CacheProbeRequest(ids: [1, 2]);
        $b = new CacheProbeRequest(ids: [2, 1]);
    } else {
        $a = new CacheProbeRequest(ids: [1, 2]);
        $b = new CacheProbeRequest(ids: [1, 2]);
        $otherClient = new TestClient($this->config->with(queryArrayFormat: QueryArrayFormat::Repeat), $this->transport);
    }
    $a->setClient($this->client);
    $b->setClient($otherClient);
    expect($a->dataOrFail())->toBe(['value' => 1])
        ->and($b->dataOrFail())->toBe(['value' => 2])
        ->and($a->dataOrFail())->toBe(['value' => 1])
        ->and($b->dataOrFail())->toBe(['value' => 2]);
})->with(['uri', 'order', 'format']);

it('автоматический ключ учитывает авторизацию и заголовки представления', function (): void {
    $a = new TestClient($this->config->with(auth: new ApiKeyAuthenticator('fixture-a', header: 'Authorization')), $this->transport);
    $b = new TestClient($this->config->with(auth: new ApiKeyAuthenticator('fixture-b', header: 'Authorization')), $this->transport);
    $requestA = (new CacheProbeRequest())->setClient($a)->withHeader('Accept-Language', 'ru');
    $requestB = (new CacheProbeRequest())->setClient($b)->withHeader('accept-language', 'ru');
    expect($requestA->dataOrFail())->toBe(['value' => 1])
        ->and($requestB->dataOrFail())->toBe(['value' => 2])
        ->and((new CacheProbeRequest())->setClient($a)->withHeader('accept-language', 'ru')->dataOrFail())->toBe(['value' => 1])
        ->and((new CacheProbeRequest())->setClient($a)->withHeader('Accept-Language', 'en')->dataOrFail())->toBe(['value' => 3]);
});

it('обычный POST не кешируется, явный opt-in учитывает тело и HTTP-метод', function (): void {
    $post = (new CachePostRequest('first'))->setClient($this->client);
    expect($post->dataOrFail())->toBe(['value' => 1])
        ->and($post->dataOrFail())->toBe(['value' => 2])
        ->and($post->withCache()->dataOrFail())->toBe(['value' => 3])
        ->and($post->withCache()->dataOrFail())->toBe(['value' => 3])
        ->and((new CachePostRequest('second'))->setClient($this->client)->withCache()->dataOrFail())->toBe(['value' => 4])
        ->and((new CacheProbeRequest())->setClient($this->client)->dataOrFail())->toBe(['value' => 5]);
});

it('custom key объединяет HTTP-варианты только внутри выбранного пространства', function (): void {
    $a = (new AttributeRichRequest('first'))->setClient($this->client)->withIdempotencyKey('one')->withCache();
    $variant = (new AttributeRichRequest('second'))->setClient($this->client)->withIdempotencyKey('two')->withCache();
    $b = $variant->withCacheScope('provider:account-b');
    expect($a->dataOrFail())->toBe(['value' => 1])
        ->and($variant->dataOrFail())->toBe(['value' => 1])
        ->and($b->dataOrFail())->toBe(['value' => 2]);
    $variant->clearCache();
    expect($a->dataOrFail())->toBe(['value' => 3])
        ->and($b->dataOrFail())->toBe(['value' => 2]);
});

it('cache hit не продлевает TTL и режимы не меняют группу очистки', function (): void {
    $store = new ControllableTimeCache(1000);
    $client = new TestClient($this->config->with(cacheConfig: new CacheConfig(store: $store, prefix: 'account', ttl: 10)), $this->transport);
    $request = (new CacheProbeRequest())->setClient($client);
    expect($request->dataOrFail())->toBe(['value' => 1]);
    $store->advance(6);
    expect($request->withCacheReadOnly()->withTraceId('another-trace')->dataOrFail())->toBe(['value' => 1]);
    $store->advance(6);
    expect($request->dataOrFail())->toBe(['value' => 2]);
    $request->withCacheReadOnly()->clearCache();
    expect($request->dataOrFail())->toBe(['value' => 3]);
});

it('очистка во время HTTP не позволяет сохранить прежний ответ', function (bool $wholeScope): void {
    $request = (new CacheProbeRequest())->setClient($this->client);
    $calls = 0;
    $client = $this->client;
    $this->transport->fake(['*' => static function () use (&$calls, $request, $client, $wholeScope): MockResponse {
        if (++$calls === 1) {
            $wholeScope ? $client->clearCache() : $request->clearCache();
        }
        return MockResponse::success(['value' => $calls]);
    }]);
    expect($request->dataOrFail())->toBe(['value' => 1])
        ->and($request->dataOrFail())->toBe(['value' => 2])
        ->and($request->dataOrFail())->toBe(['value' => 2]);
})->with([false, true]);

it('очищает варианты hook без повторного вызова hook', function (): void {
    $hook = new class implements HookInterface {
        public int $calls = 0;
        public string $language = 'ru';
        public function handle(PipelineContext $context): ?array
        {
            $this->calls++;
            $context->preparedRequest = $context->preparedRequest->withHeader('Accept-Language', $this->language);
            return null;
        }
    };
    $this->client->hooks()->on(Hook::BeforeSend, $hook);
    $request = (new CacheProbeRequest())->setClient($this->client);
    expect($request->dataOrFail())->toBe(['value' => 1]);
    $hook->language = 'en';
    expect($request->dataOrFail())->toBe(['value' => 2]);
    $request->clearCache();
    expect($hook->calls)->toBe(2);
    expect($request->dataOrFail())->toBe(['value' => 3]);
    $hook->language = 'ru';
    expect($request->dataOrFail())->toBe(['value' => 4]);
});

it('ошибочный HTTP-ответ не кешируется', function (): void {
    $this->transport->fake(['*' => MockResponse::sequence([MockResponse::serverError(), MockResponse::success(['ok' => true])])]);
    $request = (new CacheProbeRequest())->setClient($this->client);
    expect($request->send()->raw()->isFailed())->toBeTrue()
        ->and($request->dataOrFail())->toBe(['ok' => true])
        ->and($this->transport->getRecorded())->toHaveCount(2);
});

it('ошибка сохранения поколения не выдаётся за успешную очистку', function (): void {
    $this->store->failWrites = true;
    expect(fn () => $this->client->clearCache())->toThrow(ConfigurationException::class);
});

it('read-only не создаёт служебные записи при пустом кеше', function (): void {
    $this->store->failWrites = true;
    $request = (new CacheProbeRequest())->setClient($this->client);
    expect($request->withCacheReadOnly()->dataOrFail())->toBe(['value' => 1])
        ->and($this->store->keys)->toBe([]);
});

it('раздельные store и CacheConfig поддерживают кеширование', function (): void {
    $config = new ClientConfig(
        baseUrl: 'https://api.test',
        cacheConfig: new CacheConfig(store: $this->store, prefix: 'account'),
        environment: Environment::Testing,
    );
    $request = (new CacheProbeRequest())->setClient(new TestClient($config, $this->transport));
    expect($request->dataOrFail())->toBe(['value' => 1])
        ->and($request->dataOrFail())->toBe(['value' => 1])
        ->and($this->transport->getRecorded())->toHaveCount(1);
});

it('очистка не вызывает auth refresh и удаляет варианты предыдущего токена', function (): void {
    RefreshingAuthenticator::reset();
    $client = new TestClient($this->config->with(auth: new RefreshingAuthenticator()), $this->transport);
    $request = (new CacheProbeRequest())->setClient($client);
    try {
        expect($request->dataOrFail())->toBe(['value' => 1]);
        RefreshingAuthenticator::$shouldRefresh = true;
        $request->clearCache();
        expect(RefreshingAuthenticator::$refreshCalls)->toBe(0)
            ->and(RefreshingAuthenticator::$authenticateCalls)->toBe(1);
        RefreshingAuthenticator::$shouldRefresh = false;
        expect($request->dataOrFail())->toBe(['value' => 2]);
    } finally {
        RefreshingAuthenticator::reset();
    }
});

it('после auth retry не записывает ответ под ключом прежних credentials', function (): void {
    RefreshingAuthenticator::reset();
    RefreshingAuthenticator::$token = 'fixture-a';
    $store = new ArrayCache();
    $client = new TestClient($this->config->with(
        auth: new RefreshingAuthenticator(),
        cacheConfig: new CacheConfig(store: $store, prefix: 'account'),
    ), $this->transport);
    $this->transport->fake([
        CacheProbeRequest::class => MockResponse::sequence([
            MockResponse::make(['message' => 'expired'], 401),
            MockResponse::success(['value' => 1]),
            MockResponse::success(['value' => 2]),
        ]),
        RefreshTokenRequest::class => MockResponse::success(['token' => 'fixture-b']),
    ]);
    $request = (new CacheProbeRequest())->setClient($client);
    try {
        expect($request->dataOrFail())->toBe(['value' => 1])
            ->and($request->dataOrFail())->toBe(['value' => 2])
            ->and($request->dataOrFail())->toBe(['value' => 2])
            ->and($this->transport->getRecorded())->toHaveCount(4);
    } finally {
        RefreshingAuthenticator::reset();
    }
});

it('вытеснение служебных поколений не возвращает инвалидированный ответ', function (): void {
    $request = (new CacheProbeRequest())->setClient($this->client);
    expect($request->dataOrFail())->toBe(['value' => 1]);
    $request->clearCache();
    foreach ($this->store->keys as $key) {
        if (is_string($this->store->get($key))) {
            $this->store->delete($key);
        }
    }
    expect($request->dataOrFail())->toBe(['value' => 2])
        ->and($request->dataOrFail())->toBe(['value' => 2]);
});
