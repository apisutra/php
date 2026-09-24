<?php

declare(strict_types=1);

use ApiSutra\Auth\OAuth2\Internal\TokenRequest;
use ApiSutra\Auth\OAuth2\OAuth2Authenticator;
use ApiSutra\Auth\OAuth2\OAuth2Credential;
use ApiSutra\Auth\OAuth2\OAuth2FailureReason;
use ApiSutra\Auth\OAuth2\OAuth2TokenSet;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\OAuth2Config;
use ApiSutra\Exceptions\Auth\OAuth2Exception;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Auth\OAuth2\ResourceRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

it('сериализует ручные изменения credential до завершения сохранения', function (bool $retryOwner, bool $retryContender): void {
    $stored = 'initial';
    $fail = $retryOwner;
    $credential = new OAuth2Credential(new OAuth2TokenSet('initial'), onTokensChanged: function (OAuth2TokenSet $tokens) use (&$stored, &$fail): void {
        if ($fail) {
            throw new RuntimeException('store unavailable');
        }
        Fiber::suspend();
        $stored = $tokens->accessToken;
    });
    if ($retryOwner) {
        expect(fn () => $credential->replaceAuthorization(new OAuth2TokenSet('A')))->toThrow(OAuth2Exception::class);
        expect($credential->isRefreshing())->toBeFalse();
        $fail = false;
    }
    $owner = new Fiber(fn () => $retryOwner ? $credential->retryPersistence() : $credential->replaceAuthorization(new OAuth2TokenSet('A')));
    $owner->start();
    $version = $credential->tokenVersion();
    try {
        expect(fn () => $retryContender ? $credential->retryPersistence() : $credential->replaceAuthorization(new OAuth2TokenSet('B')))
            ->toThrow(ConfigurationException::class);
        expect($credential->tokens()->accessToken)->toBe('A')
            ->and($credential->tokenVersion())->toBe($version)
            ->and($credential->failureReason())->toBe(OAuth2FailureReason::TokenPersistenceFailed)
            ->and($stored)->toBe('initial');
    } finally {
        $owner->resume();
    }
    expect($stored)->toBe('A')->and($credential->tokens()->accessToken)->toBe('A')
        ->and($credential->failureReason())->toBeNull()->and($credential->isRefreshing())->toBeFalse();
    $credential->retryPersistence();
    expect($stored)->toBe('A');
})->with([false, true])->with([false, true]);

it('ручной вызов не обходит владельца автоматического refresh', function (bool $retry): void {
    $stored = null;
    $credential = new OAuth2Credential(new OAuth2TokenSet('old', 'refresh', 1), onTokensChanged: function (OAuth2TokenSet $tokens) use (&$stored): void {
        Fiber::suspend();
        $stored = $tokens;
    });
    $transport = new MockTransport();
    $transport->fake([
        TokenRequest::class => MockResponse::success(['access_token' => 'new', 'refresh_token' => 'next', 'token_type' => 'Bearer']),
        ResourceRequest::class => MockResponse::success(),
    ]);
    $auth = OAuth2Authenticator::authorizationCode(new OAuth2Config('https://id.test/token', 'id', 'secret'), $credential);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: $auth), $transport);
    $owner = new Fiber(fn () => $client->send(new ResourceRequest())->raw());
    $owner->start();
    try {
        expect(fn () => $retry ? $credential->retryPersistence() : $credential->replaceAuthorization(new OAuth2TokenSet('other')))
            ->toThrow(ConfigurationException::class);
        expect($transport->getRecorded())->toHaveCount(1)->and($stored)->toBeNull();
    } finally {
        $owner->resume();
    }
    expect($owner->getReturn()->isSuccess())->toBeTrue()->and($stored->refreshToken)->toBe('next')
        ->and($credential->failureReason())->toBeNull()->and($credential->isRefreshing())->toBeFalse();
    $transport->assertSent(TokenRequest::class, times: 1);
})->with([false, true]);

it('ошибка callback освобождает владение и сохраняет pending пару для следующей записи', function (): void {
    $fail = true;
    $credential = new OAuth2Credential(new OAuth2TokenSet('old'), onTokensChanged: function () use (&$fail): void {
        if ($fail) {
            throw new RuntimeException('store unavailable');
        }
    });
    expect(fn () => $credential->replaceAuthorization(new OAuth2TokenSet('new')))->toThrow(OAuth2Exception::class);
    expect(fn () => $credential->retryPersistence())->toThrow(OAuth2Exception::class);
    expect($credential->tokens()->accessToken)->toBe('new')->and($credential->isRefreshing())->toBeFalse()
        ->and($credential->failureReason())->toBe(OAuth2FailureReason::TokenPersistenceFailed);
    $fail = false;
    $credential->retryPersistence();
    expect($credential->failureReason())->toBeNull()->and($credential->isRefreshing())->toBeFalse();
});
