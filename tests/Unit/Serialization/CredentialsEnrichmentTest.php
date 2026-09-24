<?php

declare(strict_types=1);

use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\CredentialsEnrichmentConfig;
use ApiSutra\Config\CredentialsScopeConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Enums\Request\CredentialsMergeMode;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Serialization\Serializer;
use ApiSutra\Tests\Stubs\Enrichment\TestRequestPartsEnricher;
use ApiSutra\Tests\Stubs\Requests\CredentialsRequest;
use ApiSutra\Tests\Stubs\Requests\MultipartUploadRequest;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\Requests\SkipCredentialsRequest;
use ApiSutra\VO\Files\FileInput;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('CredentialsEnricher', function () {
    $serializerFactory = static fn (): Serializer => new Serializer(new CastRegistry());

    $contextFactory = static function (
        object $request,
        ClientConfig $config,
        ?RequestOptions $options = null,
    ): PipelineContext {
        return new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
            options: $options,
        );
    };

    it('в режиме fill-missing не перезаписывает поля request и дополняет только отсутствующие', function () use ($serializerFactory, $contextFactory) {
        $serializer = $serializerFactory();
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            environment: Environment::Testing,
            credentialsConfig: new CredentialsEnrichmentConfig(
                body: [
                    'payload.auth.login' => 'provider-login',
                    'payload.auth.client_id' => 'provider-client',
                ],
                query: [
                    'api_key' => 'provider-key',
                    'shop_id' => 'shop-1',
                ],
            ),
        );
        $request = new CredentialsRequest(
            login: 'request-login',
            apiKey: 'request-key',
        );

        $prepared = $serializer->serialize($request, $contextFactory($request, $config));
        $body = json_decode($prepared->body ?? '', true);

        expect($body['payload']['auth']['login'] ?? null)->toBe('request-login')
            ->and($body['payload']['auth']['client_id'] ?? null)->toBe('provider-client')
            ->and($prepared->meta['query']['api_key']['value'] ?? null)->toBe('request-key')
            ->and($prepared->meta['query']['shop_id']['value'] ?? null)->toBe('shop-1')
            ->and($prepared->meta['credentialsEnrichment']['applied'] ?? null)->toBeTrue();
    });

    it('в режиме overwrite перезаписывает конфликтующие поля request', function () use ($serializerFactory, $contextFactory) {
        $serializer = $serializerFactory();
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            environment: Environment::Testing,
            credentialsConfig: new CredentialsEnrichmentConfig(
                mergeMode: CredentialsMergeMode::Overwrite,
                body: ['payload.auth.login' => 'provider-login'],
                query: ['api_key' => 'provider-key'],
            ),
        );
        $request = new CredentialsRequest(
            login: 'request-login',
            apiKey: 'request-key',
        );

        $prepared = $serializer->serialize($request, $contextFactory($request, $config));
        $body = json_decode($prepared->body ?? '', true);

        expect($body['payload']['auth']['login'] ?? null)->toBe('provider-login')
            ->and($prepared->meta['query']['api_key']['value'] ?? null)->toBe('provider-key');
    });

    it('в режиме fail-on-conflict выбрасывает предсказуемую ошибку при конфликте', function () use ($serializerFactory, $contextFactory) {
        $serializer = $serializerFactory();
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            environment: Environment::Testing,
            credentialsConfig: new CredentialsEnrichmentConfig(
                mergeMode: CredentialsMergeMode::FailOnConflict,
                body: ['payload.auth.login' => 'provider-login'],
            ),
        );
        $request = new CredentialsRequest(login: 'request-login');

        expect(fn () => $serializer->serialize($request, $contextFactory($request, $config)))
            ->toThrow(ConfigurationException::class, 'Credentials enrichment conflict');
    });

    it('применяет scoped-конфиг по AuthScope атрибуту', function () use ($serializerFactory, $contextFactory) {
        $serializer = $serializerFactory();
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            environment: Environment::Testing,
            credentialsConfig: new CredentialsEnrichmentConfig(
                query: ['api_key' => 'default-key'],
                scopes: [
                    'system' => new CredentialsScopeConfig(
                        query: ['api_key' => 'system-key'],
                    ),
                ],
            ),
        );
        $request = new CredentialsRequest();

        $prepared = $serializer->serialize($request, $contextFactory($request, $config));

        expect($prepared->meta['query']['api_key']['value'] ?? null)->toBe('system-key')
            ->and($prepared->meta['credentialsEnrichment']['scope'] ?? null)->toBe('system');
    });

    it('runtime override может отключить enrichment', function () use ($serializerFactory, $contextFactory) {
        $serializer = $serializerFactory();
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            environment: Environment::Testing,
            credentialsConfig: new CredentialsEnrichmentConfig(
                body: ['plain' => 'provider-plain'],
            ),
        );
        $request = new CredentialsRequest();
        $options = RequestOptions::empty()->withoutCredentialsEnrichment();

        $prepared = $serializer->serialize($request, $contextFactory($request, $config, $options));
        $body = json_decode($prepared->body ?? '', true);

        expect($body)->toBeNull()
            ->and($prepared->meta['credentialsEnrichment'] ?? null)->toBeNull();
    });

    it('runtime override может включить enrichment даже при SkipCredentialsEnrichment', function () use ($serializerFactory, $contextFactory) {
        $serializer = $serializerFactory();
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            environment: Environment::Testing,
            credentialsConfig: new CredentialsEnrichmentConfig(
                body: ['plain' => 'provider-plain'],
            ),
        );
        $request = new SkipCredentialsRequest();
        $options = RequestOptions::empty()->withCredentialsEnrichment(true);

        $prepared = $serializer->serialize($request, $contextFactory($request, $config, $options));
        $body = json_decode($prepared->body ?? '', true);

        expect($body['plain'] ?? null)->toBe('provider-plain')
            ->and($prepared->meta['credentialsEnrichment']['applied'] ?? null)->toBeTrue();
    });

    it('runtime override merge mode имеет приоритет над config merge mode', function () use ($serializerFactory, $contextFactory) {
        $serializer = $serializerFactory();
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            environment: Environment::Testing,
            credentialsConfig: new CredentialsEnrichmentConfig(
                mergeMode: CredentialsMergeMode::FillMissing,
                body: ['payload.auth.login' => 'provider-login'],
            ),
        );
        $request = new CredentialsRequest(login: 'request-login');
        $options = RequestOptions::empty()->withCredentialsMergeMode(CredentialsMergeMode::Overwrite);

        $prepared = $serializer->serialize($request, $contextFactory($request, $config, $options));
        $body = json_decode($prepared->body ?? '', true);

        expect($body['payload']['auth']['login'] ?? null)->toBe('provider-login');
    });

    it('runtime override scope имеет приоритет над AuthScope атрибутом', function () use ($serializerFactory, $contextFactory) {
        $serializer = $serializerFactory();
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            environment: Environment::Testing,
            credentialsConfig: new CredentialsEnrichmentConfig(
                query: ['api_key' => 'default-key'],
                scopes: [
                    'system' => new CredentialsScopeConfig(query: ['api_key' => 'system-key']),
                    'runtime' => new CredentialsScopeConfig(query: ['api_key' => 'runtime-key']),
                ],
            ),
        );
        $request = new CredentialsRequest();
        $options = RequestOptions::empty()->withCredentialsScope('runtime');

        $prepared = $serializer->serialize($request, $contextFactory($request, $config, $options));

        expect($prepared->meta['query']['api_key']['value'] ?? null)->toBe('runtime-key')
            ->and($prepared->meta['credentialsEnrichment']['scope'] ?? null)->toBe('runtime');
    });

    it('подмешивает form defaults в multipart text fields', function () use ($serializerFactory, $contextFactory) {
        $serializer = $serializerFactory();
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            environment: Environment::Testing,
            credentialsConfig: new CredentialsEnrichmentConfig(
                form: ['credentials.client_id' => 'cid-1'],
            ),
        );
        $request = new MultipartUploadRequest(
            files: [FileInput::fromContent('file-content', 'doc.txt')],
            comment: 'note',
        );

        $prepared = $serializer->serialize($request, $contextFactory($request, $config));

        expect($prepared->meta['body']['comment'] ?? null)->toBe('note')
            ->and($prepared->meta['body']['credentials']['client_id'] ?? null)->toBe('cid-1')
            ->and($prepared->meta['credentialsEnrichment']['fields']['form'] ?? [])->toContain('credentials.client_id');
    });

    it('поддерживает custom request enrichers из ClientConfig', function () use ($serializerFactory, $contextFactory) {
        $serializer = $serializerFactory();
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            environment: Environment::Testing,
            requestEnrichers: [new TestRequestPartsEnricher('trace', 'abc')],
        );
        $request = new SimpleGetRequest('q');

        $prepared = $serializer->serialize($request, $contextFactory($request, $config));

        expect($prepared->url)->toContain('trace=abc');
    });
});
