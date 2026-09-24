<?php

declare(strict_types=1);

use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Auth\RecordingAuthenticator;
use ApiSutra\Tests\Stubs\Requests\AuthRequest;
use ApiSutra\Tests\Stubs\Requests\NoAuthRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\ArrayCache;
use ApiSutra\Transport\MockTransport;

describe('AuthHandler', function () {
    it('применяет аутентификатор и кеш для обычного запроса', function () {
        RecordingAuthenticator::reset();
        $cache = new ArrayCache();

        $auth = new RecordingAuthenticator();
        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            auth: $auth,
            cacheConfig: new CacheConfig(store: $cache),
            environment: Environment::Testing,
        );

        $transport = new MockTransport();
        $transport->fake([
            AuthRequest::class => MockResponse::success(['id' => 1, 'name' => 'ok']),
        ]);

        $client = new TestClient($config, $transport);
        $request = new AuthRequest('payload');
        $request->setClient($client);
        $request->send();

        $recorded = $transport->getRecorded();
        expect($recorded)->toHaveCount(1)
            ->and($recorded[0]->headers['X-Auth'] ?? null)->toBe('token')
            ->and(RecordingAuthenticator::$authenticateCalls)->toBe(1)
            ->and(RecordingAuthenticator::$cache)->not->toBe($cache);
        RecordingAuthenticator::$cache->set('fixture-token', 'local');
        expect($cache->get('fixture-token'))->toBeNull()
            ->and(RecordingAuthenticator::$cache->get('fixture-token'))->toBe('local');
    });

    it('не применяет аутентификатор для NoAuth', function () {
        RecordingAuthenticator::reset();
        $auth = new RecordingAuthenticator();

        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            auth: $auth,
            environment: Environment::Testing,
        );

        $transport = new MockTransport();
        $transport->fake([
            NoAuthRequest::class => MockResponse::success(['id' => 1, 'name' => 'ok']),
        ]);

        $client = new TestClient($config, $transport);
        $request = new NoAuthRequest('payload');
        $request->setClient($client);
        $request->send();

        $recorded = $transport->getRecorded();
        expect($recorded)->toHaveCount(1)
            ->and($recorded[0]->headers['X-Auth'] ?? null)->toBeNull()
            ->and(RecordingAuthenticator::$authenticateCalls)->toBe(0);
    });

    it('не применяет аутентификатор при withAuth для NoAuth', function () {
        RecordingAuthenticator::reset();
        $auth = new RecordingAuthenticator();

        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            auth: $auth,
            environment: Environment::Testing,
        );

        $transport = new MockTransport();
        $transport->fake([
            NoAuthRequest::class => MockResponse::success(['id' => 1, 'name' => 'ok']),
        ]);

        $client = new TestClient($config, $transport);
        $request = new NoAuthRequest('payload');
        $request->setClient($client)->withAuth()->send();

        $recorded = $transport->getRecorded();
        expect($recorded)->toHaveCount(1)
            ->and($recorded[0]->headers['X-Auth'] ?? null)->toBeNull()
            ->and(RecordingAuthenticator::$authenticateCalls)->toBe(0);
    });

    it('применяет аутентификатор при forceAuth для NoAuth', function () {
        RecordingAuthenticator::reset();
        $auth = new RecordingAuthenticator();

        $config = new ClientConfig(
            baseUrl: 'https://provider.test',
            auth: $auth,
            environment: Environment::Testing,
        );

        $transport = new MockTransport();
        $transport->fake([
            NoAuthRequest::class => MockResponse::success(['id' => 1, 'name' => 'ok']),
        ]);

        $client = new TestClient($config, $transport);
        $request = new NoAuthRequest('payload');
        $request->setClient($client)->forceAuth()->send();

        $recorded = $transport->getRecorded();
        expect($recorded)->toHaveCount(1)
            ->and($recorded[0]->headers['X-Auth'] ?? null)->toBe('token')
            ->and(RecordingAuthenticator::$authenticateCalls)->toBe(1);
    });
});
