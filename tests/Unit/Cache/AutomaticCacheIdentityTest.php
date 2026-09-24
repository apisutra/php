<?php

declare(strict_types=1);

use ApiSutra\Auth\ApiKeyAuthenticator;
use ApiSutra\Auth\AuthorizationSchemeAuthenticator;
use ApiSutra\Auth\BasicAuthenticator;
use ApiSutra\Auth\BearerAuthenticator;
use ApiSutra\Auth\HmacAuthenticator;
use ApiSutra\Auth\TokenAuthenticator;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Core\AbstractClient;
use ApiSutra\Tests\Stubs\Auth\SignatureParamsProvider;
use ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\CacheIdentity;
use ApiSutra\Tests\Stubs\Auth\NamedAuthenticator;
use ApiSutra\Tests\Stubs\Requests\AttributeRichRequest;
use ApiSutra\Tests\Stubs\Requests\TenantCacheRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\StrictCache;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Pipeline\PipelineContext;

beforeEach(function (): void {
    $this->store = new StrictCache();
    $this->transport = new MockTransport();
    $this->calls = 0;
    $this->transport->fake(['*' => function (): MockResponse {
        return MockResponse::success(['value' => ++$this->calls]);
    }]);
    $this->config = new ClientConfig(
        baseUrl: 'https://api.test',
        cacheConfig: new CacheConfig(store: $this->store),
        environment: Environment::Testing,
    );
});

it('автоматически разделяет custom key по credentials и разделяет кеш одинаковых клиентов', function (string $kind): void {
    $auth = static fn (string $credential): AuthenticatorInterface => match ($kind) {
        'bearer' => new BearerAuthenticator($credential),
        'basic' => new BasicAuthenticator('fixture-user', $credential),
        'api-header' => new ApiKeyAuthenticator($credential, header: 'X-Custom-Auth'),
        'api-query' => new ApiKeyAuthenticator($credential, header: null, query: 'custom_auth'),
        'hmac' => new HmacAuthenticator('fixture-key', $credential),
        'scheme' => new AuthorizationSchemeAuthenticator('Token', token: $credential),
        'params' => new AuthorizationSchemeAuthenticator('Token', params: ['credential' => $credential]),
        'token' => new TokenAuthenticator('fixture_user', $credential),
    };
    $this->config = $this->config->with(authRetryAttempts: 0);
    $a = new TestClient($this->config->with(auth: $auth('fixture-a')), $this->transport);
    $b = new TestClient($this->config->with(auth: $auth('fixture-b')), $this->transport);
    $same = new TestClient($this->config->with(auth: $auth('fixture-a')), $this->transport);
    $request = (new TenantCacheRequest())->setClient($a);
    $other = (new TenantCacheRequest())->setClient($b);
    expect($request->dataOrFail())->toBe(['value' => 1])
        ->and($other->dataOrFail())->toBe(['value' => 2])
        ->and((new TenantCacheRequest())->setClient($same)->withHeader('Accept-Language', 'en')->dataOrFail())->toBe(['value' => 1]);
    $a->clearCache();
    expect($other->dataOrFail())->toBe(['value' => 2])
        ->and($request->dataOrFail())->toBe(['value' => 3]);
    foreach ($this->store->keys as $key) {
        expect($key)->toMatch('/^[a-f0-9]{64}$/');
    }
})->with(['bearer', 'basic', 'api-header', 'api-query', 'hmac', 'scheme', 'params', 'token']);

it('одинаковый prefix или runtime scope не объединяет разные identity', function (bool $runtime): void {
    $requests = [];
    foreach (['fixture-a', 'fixture-b'] as $token) {
        $client = new TestClient($this->config->with(
            auth: new BearerAuthenticator($token),
            cacheConfig: new CacheConfig(store: $this->store, prefix: 'shared'),
        ), $this->transport);
        $request = (new TenantCacheRequest())->setClient($client);
        $requests[] = $runtime ? $request->withCacheScope('same') : $request;
    }
    expect($requests[0]->dataOrFail())->toBe(['value' => 1])
        ->and($requests[1]->dataOrFail())->toBe(['value' => 2])
        ->and($requests[0]->dataOrFail())->toBe(['value' => 1]);
})->with([false, true]);

it('разделяет tenant одного аккаунта и очищает только выбранную группу', function (): void {
    $client = new TestClient($this->config->with(auth: new BearerAuthenticator('fixture')), $this->transport);
    $a = (new TenantCacheRequest('a'))->setClient($client);
    $b = (new TenantCacheRequest('b'))->setClient($client);
    expect($a->dataOrFail())->toBe(['value' => 1])
        ->and($b->dataOrFail())->toBe(['value' => 2]);
    $a->clearCache();
    expect($b->dataOrFail())->toBe(['value' => 2])
        ->and($a->dataOrFail())->toBe(['value' => 3]);
    $client->clearCache();
    expect($b->dataOrFail())->toBe(['value' => 4]);
});

it('конфигурационный tenant и store сохраняются при атрибуте Cache', function (): void {
    $a = new TestClient($this->config->with(cacheConfig: $this->config->cacheConfig->with(identity: new CacheIdentity('a'))), $this->transport);
    $b = new TestClient($this->config->with(cacheConfig: $this->config->cacheConfig->with(identity: new CacheIdentity('b'))), $this->transport);
    $request = (new TenantCacheRequest())->setClient($a);
    $other = (new TenantCacheRequest())->setClient($b);
    expect($request->dataOrFail())->toBe(['value' => 1])
        ->and($other->dataOrFail())->toBe(['value' => 2]);
    $a->clearCache();
    expect($other->dataOrFail())->toBe(['value' => 2])
        ->and($request->dataOrFail())->toBe(['value' => 3]);
});

it('пропускает кеш при неизвестной identity независимо от prefix', function (string $source): void {
    $config = $this->config->with(cacheConfig: new CacheConfig(store: $this->store, prefix: 'explicit', identity: $source === 'config' ? new CacheIdentity(null) : null));
    if ($source === 'auth') {
        $config = $config->with(auth: new NamedAuthenticator('fixture'));
    }
    $request = (new TenantCacheRequest($source === 'request' ? null : 'a'))->setClient(new TestClient($config, $this->transport));
    expect($request->dataOrFail())->toBe(['value' => 1])
        ->and($request->dataOrFail())->toBe(['value' => 2])
        ->and($this->store->keys)->toBe([]);
})->with(['auth', 'config', 'request']);

it('использует фактический auth scope и NoAuth без смешения custom key', function (): void {
    $client = new TestClient($this->config->with(
        auth: new BearerAuthenticator('fixture-a'),
        authScopes: ['secondary' => new BearerAuthenticator('fixture-b'), 'alias' => new BearerAuthenticator('fixture-a')],
    ), $this->transport);
    $request = (new TenantCacheRequest())->setClient($client);
    expect($request->dataOrFail())->toBe(['value' => 1])
        ->and($request->withAuthScope('alias')->dataOrFail())->toBe(['value' => 1])
        ->and($request->withAuthScope('secondary')->dataOrFail())->toBe(['value' => 2])
        ->and($request->withoutAuth()->dataOrFail())->toBe(['value' => 3])
        ->and($request->withAuthScope('secondary')->withAuth()->dataOrFail())->toBe(['value' => 1]);
    $client->clearCache();
    expect($request->withAuthScope('secondary')->dataOrFail())->toBe(['value' => 4]);
    $noAuth = (new AttributeRichRequest('a'))->setClient($client)->withIdempotencyKey('fixture');
    expect($noAuth->dataOrFail())->toBe(['value' => 5])
        ->and($noAuth->forceAuth()->dataOrFail())->toBe(['value' => 6])
        ->and($noAuth->dataOrFail())->toBe(['value' => 5]);
});

it('не смешивает custom key разных базовых URL', function (): void {
    $a = (new TenantCacheRequest())->setClient(new TestClient($this->config, $this->transport));
    $b = (new TenantCacheRequest())->setClient(new TestClient($this->config->with(baseUrl: 'https://other.test'), $this->transport));
    expect($a->dataOrFail())->toBe(['value' => 1])
        ->and($b->dataOrFail())->toBe(['value' => 2]);
});

it('разделяет custom key при вручную переданных credentials', function (string $header): void {
    $client = new TestClient($this->config, $this->transport);
    $request = (new TenantCacheRequest())->setClient($client);
    expect($request->withHeader($header, 'fixture-a')->dataOrFail())->toBe(['value' => 1])
        ->and($request->withHeader($header, 'fixture-b')->dataOrFail())->toBe(['value' => 2])
        ->and($request->withHeader($header, 'fixture-a')->dataOrFail())->toBe(['value' => 1]);
})->with(['Authorization', 'Cookie', 'X-Api-Key']);

it('разделяет custom кеш при изменении credentials в hook даже с нестандартным header', function (): void {
    $client = new TestClient($this->config->with(auth: new ApiKeyAuthenticator('fixture-a', header: 'X-Custom-Auth')), $this->transport);
    $hook = new class implements HookInterface {
        public bool $replace = false;
        public function handle(PipelineContext $context): ?array
        {
            if ($this->replace) {
                $context->preparedRequest = $context->preparedRequest->withHeader('X-Custom-Auth', 'fixture-b');
            }
            return null;
        }
    };
    $client->hooks()->on(Hook::BeforeSend, $hook);
    $request = (new TenantCacheRequest())->setClient($client)->withHeader('X-Custom-Auth', 'fixture-a');
    expect($request->dataOrFail())->toBe(['value' => 1]);
    $hook->replace = true;
    expect($request->dataOrFail())->toBe(['value' => 2])
        ->and($request->dataOrFail())->toBe(['value' => 2]);
    $hook->replace = false;
    expect($request->dataOrFail())->toBe(['value' => 1]);
});

it('разделяет классы SDK-клиентов даже с одинаковым URL и credentials', function (): void {
    $config = $this->config->with(auth: new BearerAuthenticator('fixture'));
    $a = new TestClient($config, $this->transport);
    $b = new class($config, $this->transport) extends AbstractClient {};
    $request = (new TenantCacheRequest())->setClient($a);
    $other = (new TenantCacheRequest())->setClient($b);
    expect($request->dataOrFail())->toBe(['value' => 1])
        ->and($other->dataOrFail())->toBe(['value' => 2]);
    $a->clearCache();
    expect($other->dataOrFail())->toBe(['value' => 2]);
});

it('не восстанавливает запись после смены tenant во время HTTP', function (): void {
    $client = new TestClient($this->config, $this->transport);
    $request = (new TenantCacheRequest('a'))->setClient($client);
    $calls = 0;
    $this->transport->fake(['*' => static function () use ($request, &$calls): MockResponse {
        $request->tenant = 'b';
        return MockResponse::success(['value' => ++$calls]);
    }]);
    expect($request->dataOrFail())->toBe(['value' => 1]);
    $request->tenant = 'a';
    expect($request->dataOrFail())->toBe(['value' => 2]);
});

it('не кеширует динамическую AuthorizationScheme без достоверной identity', function (): void {
    $auth = new AuthorizationSchemeAuthenticator(
        'Signature',
        provider: new SignatureParamsProvider('fixture-secret', 123),
    );
    $client = new TestClient($this->config->with(auth: $auth), $this->transport);
    $request = (new TenantCacheRequest())->setClient($client);
    expect($request->dataOrFail())->toBe(['value' => 1])
        ->and($request->dataOrFail())->toBe(['value' => 2])
        ->and($this->store->keys)->toBe([]);
});

it('разделяет итоговый tenant из hook, сохраняя custom key для обычных заголовков', function (): void {
    $client = new TestClient($this->config->with(auth: new BearerAuthenticator('fixture')), $this->transport);
    $hook = new class implements HookInterface {
        public string $tenant = 'a';
        public string $language = 'ru';
        public function handle(PipelineContext $context): ?array
        {
            $context->preparedRequest = $context->preparedRequest
                ->withHeader('X-Tenant-Id', $this->tenant)
                ->withHeader('Accept-Language', $this->language);
            return null;
        }
    };
    $client->hooks()->on(Hook::BeforeSend, $hook);
    $request = (new TenantCacheRequest('a'))->setClient($client);
    expect($request->dataOrFail())->toBe(['value' => 1]);
    $hook->language = 'en';
    expect($request->dataOrFail())->toBe(['value' => 1]);
    $hook->tenant = 'b';
    expect($request->dataOrFail())->toBe(['value' => 2]);
    $hook->tenant = 'a';
    expect($request->dataOrFail())->toBe(['value' => 1]);
});

it('разделяет нестандартные query credentials и origin после подготовки', function (): void {
    $client = new TestClient($this->config->with(auth: new ApiKeyAuthenticator('fixture-a', header: null, query: 'custom_auth')), $this->transport);
    $hook = new class implements HookInterface {
        public bool $replace = false;
        public function handle(PipelineContext $context): ?array
        {
            if ($this->replace) {
                $context->preparedRequest = $context->preparedRequest->with(
                    url: str_replace('custom_auth=fixture-a', 'custom_auth=fixture-b', $context->preparedRequest->url),
                );
            }
            return null;
        }
    };
    $client->hooks()->on(Hook::BeforeSend, $hook);
    $request = (new TenantCacheRequest())->setClient($client);
    expect($request->dataOrFail())->toBe(['value' => 1]);
    $hook->replace = true;
    expect($request->dataOrFail())->toBe(['value' => 2])
        ->and($request->dataOrFail())->toBe(['value' => 2]);
    $hook->replace = false;
    expect($request->dataOrFail())->toBe(['value' => 1])
        ->and($request->withBaseUrl('https://other.test')->dataOrFail())->toBe(['value' => 3]);
});
