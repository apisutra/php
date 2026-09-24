<?php

declare(strict_types=1);

use ApiSutra\Auth\OAuth2\AuthorizationAttempt;
use ApiSutra\Auth\OAuth2\AuthorizationCodeFlow;
use ApiSutra\Auth\OAuth2\ClientAuthentication;
use ApiSutra\Auth\OAuth2\OAuth2TokenSet;
use ApiSutra\Auth\OAuth2\OAuth2Authenticator;
use ApiSutra\Auth\OAuth2\OAuth2Credential;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Config\OAuth2Config;
use ApiSutra\Exceptions\Auth\OAuth2Exception;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Tests\Support\VirtualClock;

it('создаёт независимые PKCE S256 попытки и восстанавливает исходные сроки', function (): void {
    $clock = new VirtualClock();
    $flow = new AuthorizationCodeFlow(new OAuth2Config('https://id.test/token', 'public', clientAuthentication: ClientAuthentication::None, scopes: ['b', 'a', 'b']), 'https://id.test/authorize', 'https://app.test/callback', clock: $clock);
    $a = $flow->begin();
    $b = $flow->begin();
    parse_str(parse_url($a->url, PHP_URL_QUERY), $query);
    expect($a->state)->not->toBe($b->state)->and($a->codeVerifier)->not->toBe($b->codeVerifier)
        ->and($query['code_challenge'])->toBe(rtrim(strtr(base64_encode(hash('sha256', $a->codeVerifier, true)), '+/', '-_'), '='))
        ->and($query['scope'])->toBe('a b')->and($query)->not->toHaveKey('code_verifier');
    $snapshot = json_decode(json_encode($a->export()), true);
    $clock->advance(601000);
    $restored = AuthorizationAttempt::restore($snapshot);
    expect($restored->expiresAt)->toBe($a->expiresAt);
    expect(fn () => $flow->exchange($restored, ['state' => $a->state, 'code' => 'secret'], 'https://app.test/callback'))->toThrow(OAuth2Exception::class);
});

it('отклоняет неподходящий callback до создания HTTP запроса', function (string $case): void {
    $config = new OAuth2Config('https://id.test/token', 'id', 'secret');
    $flow = new AuthorizationCodeFlow($config, 'https://id.test/authorize', 'https://app.test/callback', expectedIssuer: 'https://id.test');
    $attempt = $flow->begin();
    $parameters = ['state' => $attempt->state, 'code' => 'secret-code', 'iss' => 'https://id.test'];
    $uri = 'https://app.test/callback';
    match ($case) {
        'state' => $parameters['state'] = 'wrong',
        'array' => $parameters['code'] = ['first', 'second'],
        'issuer' => $parameters['iss'] = 'https://other.test',
        'missing-issuer' => $parameters = array_diff_key($parameters, ['iss' => true]),
        'error' => $parameters['error'] = 'access_denied',
        'empty-code' => $parameters['code'] = '',
        'redirect' => $uri = 'https://app.test/other',
        'flow' => $flow = new AuthorizationCodeFlow($config, 'https://other.test/authorize', 'https://app.test/callback'),
    };
    try {
        $flow->exchange($attempt, $parameters, $uri);
        test()->fail('Callback должен быть отклонён');
    } catch (OAuth2Exception $exception) {
        expect($exception->reason->value)->toBe('oauth2_invalid_callback')->and($exception->getMessage())->not->toContain('secret-code');
    }
})->with(['state', 'array', 'issuer', 'missing-issuer', 'error', 'empty-code', 'redirect', 'flow']);

it('проверяет конфигурацию и зарезервированные параметры без HTTP', function (string $case): void {
    expect(fn () => match ($case) {
        'http' => new OAuth2Config('http://id.test/token', 'id', 'secret'),
        'userinfo' => new OAuth2Config('https://secret@id.test/token', 'id', 'secret'),
        'reserved-query' => new OAuth2Config('https://id.test/token?client_id=other', 'id', 'secret'),
        'reserved-extra' => new OAuth2Config('https://id.test/token', 'id', 'secret', tokenParameters: ['grant_type' => 'password']),
        'scope' => new OAuth2Config('https://id.test/token', 'id', 'secret', scopes: ['space invalid']),
        'implicit-public' => new OAuth2Config('https://id.test/token', 'id'),
    })->toThrow(ConfigurationException::class);
})->with(['http', 'userinfo', 'reserved-query', 'reserved-extra', 'scope', 'implicit-public']);

it('snapshot токенов сохраняет абсолютный срок и различает неизвестные и пустые права', function (): void {
    foreach ([null, [], ['a', 'b']] as $scopes) {
        $tokens = new OAuth2TokenSet('access', 'refresh', 100, $scopes, 95);
        $restored = OAuth2TokenSet::restore(json_decode(json_encode($tokens->export()), true));
        expect($restored->export())->toBe($tokens->export())->and($restored->isExpired(101))->toBeTrue();
    }
    expect(fn () => OAuth2TokenSet::restore(['version' => 2]))->toThrow(ConfigurationException::class);
});

it('порядок дополнительных параметров не меняет привязку credential и attempt', function (): void {
    $a = new OAuth2Config('https://id.test/token', 'id', 'secret', tokenParameters: ['audience' => 'api', 'resource' => 'records']);
    $b = new OAuth2Config('https://id.test/token', 'id', 'secret', tokenParameters: ['resource' => 'records', 'audience' => 'api']);
    expect($a->grantIdentity())->toBe($b->grantIdentity());
    $credential = new OAuth2Credential(new OAuth2TokenSet('access'));
    OAuth2Authenticator::authorizationCode($a, $credential);
    OAuth2Authenticator::authorizationCode($b, $credential);
    $first = new AuthorizationCodeFlow($a, 'https://id.test/authorize', 'https://app.test/callback', authorizationParameters: ['prompt' => 'consent', 'display' => 'page']);
    $second = new AuthorizationCodeFlow($b, 'https://id.test/authorize', 'https://app.test/callback', authorizationParameters: ['display' => 'page', 'prompt' => 'consent']);
    $attempt = AuthorizationAttempt::restore(json_decode(json_encode($first->begin()->export()), true));
    $callback = ['state' => $attempt->state, 'code' => 'fixture'];
    expect($second->exchange($attempt, $callback, 'https://app.test/callback'))->toBeInstanceOf(AbstractRequest::class);

    $other = new OAuth2Config('https://id.test/token', 'id', 'secret', tokenParameters: ['audience' => 'other', 'resource' => 'records']);
    expect($other->grantIdentity())->not->toBe($a->grantIdentity());
    expect(fn () => OAuth2Authenticator::authorizationCode($other, $credential))->toThrow(ConfigurationException::class);
    $otherFlow = new AuthorizationCodeFlow($b, 'https://id.test/authorize', 'https://app.test/callback', authorizationParameters: ['display' => 'page', 'prompt' => 'login']);
    expect(fn () => $otherFlow->exchange($attempt, $callback, 'https://app.test/callback'))->toThrow(OAuth2Exception::class);
});
