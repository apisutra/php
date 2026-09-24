<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Auth\AllowListAuthPolicy;
use ApiSutra\Tests\Stubs\Auth\NamedAuthenticator;
use ApiSutra\Tests\Stubs\Auth\RecordingAuthenticator;
use ApiSutra\Tests\Stubs\Requests\AuthRequest;
use ApiSutra\Tests\Stubs\Requests\NoAuthRequest;
use ApiSutra\Tests\Stubs\Requests\ScopedAuthEnumRequest;
use ApiSutra\Tests\Stubs\Requests\ScopedAuthRequest;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

describe('Auth policy and scope', function () {
    it('применяет auth только для allowlist запросов', function () {
        RecordingAuthenticator::reset();

        $transport = new MockTransport();
        $transport->fake([
            AuthRequest::class => MockResponse::success(['ok' => true]),
            SimpleGetRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: new RecordingAuthenticator(),
            authPolicy: new AllowListAuthPolicy([AuthRequest::class]),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        (new AuthRequest('payload'))->setClient($client)->send();
        (new SimpleGetRequest('q'))->setClient($client)->send();

        expect(RecordingAuthenticator::$authenticateCalls)->toBe(1);
    });

    it('использует AuthScope поверх политики', function () {
        NamedAuthenticator::reset();

        $transport = new MockTransport();
        $transport->fake([
            ScopedAuthRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: new NamedAuthenticator('default'),
            authScopes: [
                'system' => new NamedAuthenticator('system'),
            ],
            authPolicy: new AllowListAuthPolicy([]),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        (new ScopedAuthRequest())->setClient($client)->send();

        expect(NamedAuthenticator::$calls['system'] ?? 0)->toBe(1)
            ->and(NamedAuthenticator::$calls['default'] ?? 0)->toBe(0);
    });

    it('использует AuthScope enum-case поверх политики', function () {
        NamedAuthenticator::reset();

        $transport = new MockTransport();
        $transport->fake([
            ScopedAuthEnumRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: new NamedAuthenticator('default'),
            authScopes: [
                'system' => new NamedAuthenticator('system'),
            ],
            authPolicy: new AllowListAuthPolicy([]),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        (new ScopedAuthEnumRequest())->setClient($client)->send();

        expect(NamedAuthenticator::$calls['system'] ?? 0)->toBe(1)
            ->and(NamedAuthenticator::$calls['default'] ?? 0)->toBe(0);
    });

    it('runtime withAuth перекрывает политику', function () {
        RecordingAuthenticator::reset();

        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: new RecordingAuthenticator(),
            authPolicy: new AllowListAuthPolicy([]),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        (new SimpleGetRequest('q'))->setClient($client)->withAuth()->send();

        expect(RecordingAuthenticator::$authenticateCalls)->toBe(1);
    });

    it('runtime withAuthScope перекрывает политику', function () {
        NamedAuthenticator::reset();

        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: new NamedAuthenticator('default'),
            authScopes: [
                'system' => new NamedAuthenticator('system'),
            ],
            authPolicy: new AllowListAuthPolicy([]),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        (new SimpleGetRequest('q'))->setClient($client)->withAuthScope('system')->send();

        expect(NamedAuthenticator::$calls['system'] ?? 0)->toBe(1)
            ->and(NamedAuthenticator::$calls['default'] ?? 0)->toBe(0);
    });

    it('forceAuthScope пробивает NoAuth', function () {
        NamedAuthenticator::reset();

        $transport = new MockTransport();
        $transport->fake([
            NoAuthRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: new NamedAuthenticator('default'),
            authScopes: [
                'system' => new NamedAuthenticator('system'),
            ],
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        (new NoAuthRequest('payload'))->setClient($client)->forceAuthScope('system')->send();

        expect(NamedAuthenticator::$calls['system'] ?? 0)->toBe(1)
            ->and(NamedAuthenticator::$calls['default'] ?? 0)->toBe(0);
    });

    it('runtime withoutAuth перекрывает AuthScope', function () {
        NamedAuthenticator::reset();

        $transport = new MockTransport();
        $transport->fake([
            ScopedAuthRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: new NamedAuthenticator('default'),
            authScopes: [
                'system' => new NamedAuthenticator('system'),
            ],
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        (new ScopedAuthRequest())->setClient($client)->withoutAuth()->send();

        expect(NamedAuthenticator::$calls)->toBe([]);
    });

    it('runtime withAuthScope перекрывает AuthScope атрибут', function () {
        NamedAuthenticator::reset();

        $transport = new MockTransport();
        $transport->fake([
            ScopedAuthRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: new NamedAuthenticator('default'),
            authScopes: [
                'system' => new NamedAuthenticator('system'),
                'custom' => new NamedAuthenticator('custom'),
            ],
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        (new ScopedAuthRequest())->setClient($client)->withAuthScope('custom')->send();

        expect(NamedAuthenticator::$calls['custom'] ?? 0)->toBe(1)
            ->and(NamedAuthenticator::$calls['system'] ?? 0)->toBe(0)
            ->and(NamedAuthenticator::$calls['default'] ?? 0)->toBe(0);
    });

    it('withAuth требует auth в конфиге', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            throwOnErrors: true,
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);

        expect(fn () => (new SimpleGetRequest('q'))->setClient($client)->withAuth()->send())
            ->toThrow(ConfigurationException::class, 'Auth is enabled but not configured');
    });

    it('withAuthScope требует scope в конфиге', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: new NamedAuthenticator('default'),
            throwOnErrors: true,
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);

        expect(fn () => (new SimpleGetRequest('q'))->setClient($client)->withAuthScope('system')->send())
            ->toThrow(ConfigurationException::class, 'Auth scope \'system\' not found in config');
    });

    it('forceAuth требует auth в конфиге', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            throwOnErrors: true,
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);

        expect(fn () => (new SimpleGetRequest('q'))->setClient($client)->forceAuth()->send())
            ->toThrow(ConfigurationException::class, 'Auth is enabled but not configured');
    });

    it('forceAuthScope требует scope в конфиге', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: new NamedAuthenticator('default'),
            throwOnErrors: true,
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);

        expect(fn () => (new SimpleGetRequest('q'))->setClient($client)->forceAuthScope('system')->send())
            ->toThrow(ConfigurationException::class, 'Auth scope \'system\' not found in config');
    });

    it('AuthPolicy требует auth для allowlist запросов', function () {
        $transport = new MockTransport();
        $transport->fake([
            AuthRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            authPolicy: new AllowListAuthPolicy([AuthRequest::class]),
            throwOnErrors: true,
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);

        expect(fn () => (new AuthRequest('payload'))->setClient($client)->send())
            ->toThrow(ConfigurationException::class, 'AuthPolicy is set but auth is not configured');
    });

    it('AuthPolicy не требует auth для запросов вне allowlist', function () {
        RecordingAuthenticator::reset();

        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            authPolicy: new AllowListAuthPolicy([AuthRequest::class]),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        (new SimpleGetRequest('q'))->setClient($client)->send();

        expect($transport->getRecorded())->toHaveCount(1);
    });
});
