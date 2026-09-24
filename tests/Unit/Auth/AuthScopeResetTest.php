<?php

declare(strict_types=1);

use ApiSutra\Auth\ApiKeyAuthenticator;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\NoAuthRequest;
use ApiSutra\Tests\Stubs\Requests\RuntimeScopedRequest;
use ApiSutra\Tests\Stubs\Requests\ScopedAuthRequest;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Http\PreparedRequest;

it('сбрасывает runtime scope без изменения исходной цепочки', function (string $method): void {
    $transport = new MockTransport();
    $transport->fake([SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'Example'])]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.test',
        auth: new ApiKeyAuthenticator('fixture-a', header: 'Authorization'),
        authScopes: ['secondary' => new ApiKeyAuthenticator('fixture-b', header: 'Authorization')],
        environment: Environment::Testing,
    ), $transport);
    $request = (new SimpleGetRequest('q'))->setClient($client);
    $secondary = $request->withAuthScope('secondary')->withCache(120)->withTimeout(42, 7);
    $reset = $secondary->{$method}()->withAuth()->withCache()->withTimeout(10);

    expect($request->send()->raw()->isSuccess())->toBeTrue()
        ->and($secondary->send()->raw()->isSuccess())->toBeTrue()
        ->and($reset->send()->raw()->isSuccess())->toBeTrue()
        ->and($secondary->send()->raw()->isSuccess())->toBeTrue();

    $headers = array_map(
        static fn (PreparedRequest $record): ?string => $record->headers['Authorization'] ?? null,
        $transport->getRecorded(),
    );
    expect($headers)->toBe(['fixture-a', 'fixture-b', 'fixture-a', 'fixture-b'])
        ->and($reset->getOptions()->getAuthScopeOverride())->toBeNull()
        ->and($secondary->getOptions()->getAuthScopeOverride())->toBe('secondary')
        ->and($reset->getOptions()->getCacheOverride()->ttl)->toBe(120)
        ->and($reset->getOptions()->getConnectTimeoutOverride())->toBeNull()
        ->and($secondary->getOptions()->getConnectTimeoutOverride())->toBe(7);
})->with(['withAuth', 'withoutAuth', 'forceAuth']);

it('не восстанавливает runtime scope исходного request после замены execution options', function (): void {
    $transport = new MockTransport();
    $transport->fake([RuntimeScopedRequest::class => MockResponse::success(['ok' => true])]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.test',
        auth: new ApiKeyAuthenticator('fixture-a', header: 'Authorization'),
        authScopes: ['secondary' => new ApiKeyAuthenticator('fixture-b', header: 'Authorization')],
        environment: Environment::Testing,
    ), $transport);
    $request = (new RuntimeScopedRequest())->setClient($client);

    $request->send();
    $request->withOptions(RequestOptions::empty()->withAuth())->send();
    $request->send();

    expect($transport->getRecorded()[0]->headers['Authorization'])->toBe('fixture-b')
        ->and($transport->getRecorded()[1]->headers['Authorization'])->toBe('fixture-a')
        ->and($transport->getRecorded()[2]->headers['Authorization'])->toBe('fixture-b');
});

it('после сброса runtime scope сохраняет AuthScope атрибут', function (): void {
    $transport = new MockTransport();
    $transport->fake([ScopedAuthRequest::class => MockResponse::success(['ok' => true])]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.test',
        auth: new ApiKeyAuthenticator('fixture-default', header: 'Authorization'),
        authScopes: [
            'system' => new ApiKeyAuthenticator('fixture-system', header: 'Authorization'),
            'secondary' => new ApiKeyAuthenticator('fixture-secondary', header: 'Authorization'),
        ],
        environment: Environment::Testing,
    ), $transport);

    (new ScopedAuthRequest())->setClient($client)->withAuthScope('secondary')->withAuth()->send();

    expect($transport->getRecorded()[0]->headers['Authorization'])->toBe('fixture-system');
});

it('сброс scope сохраняет правила NoAuth и forceAuth', function (): void {
    $transport = new MockTransport();
    $transport->fake([NoAuthRequest::class => MockResponse::success(['ok' => true])]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.test',
        auth: new ApiKeyAuthenticator('fixture-a', header: 'Authorization'),
        authScopes: ['secondary' => new ApiKeyAuthenticator('fixture-b', header: 'Authorization')],
        environment: Environment::Testing,
    ), $transport);
    $request = (new NoAuthRequest('q'))->setClient($client);

    $request->withAuthScope('secondary')->withAuth()->send();
    $request->forceAuthScope('secondary')->forceAuth()->send();
    $request->forceAuthScope('secondary')->withoutAuth()->send();

    expect($transport->getRecorded()[0]->headers)->not->toHaveKey('Authorization')
        ->and($transport->getRecorded()[1]->headers['Authorization'])->toBe('fixture-a')
        ->and($transport->getRecorded()[2]->headers)->not->toHaveKey('Authorization');
});
