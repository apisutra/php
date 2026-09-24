<?php

declare(strict_types=1);

use ApiSutra\Auth\Authorization\QueryLikeFormatter;
use ApiSutra\Auth\Authorization\QuotedCommaFormatter;
use ApiSutra\Auth\AuthorizationSchemeAuthenticator;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Tests\Stubs\Auth\SignatureParamsProvider;
use ApiSutra\VO\Http\PreparedRequest;

describe('AuthorizationSchemeAuthenticator', function () {
    it('собирает заголовок с token', function () {
        $auth = new AuthorizationSchemeAuthenticator('Token', token: 'abc');
        $request = new PreparedRequest(HttpMethod::GET, 'https://api.test');

        $result = $auth->authenticate($request);

        expect($result->headers['Authorization'] ?? null)->toBe('Token abc');
    });

    it('собирает заголовок из параметров и экранирует значения', function () {
        $auth = new AuthorizationSchemeAuthenticator('Signature', params: [
            'key' => 'id',
            'note' => 'a"b\\c',
            'flag' => true,
        ], formatter: new QuotedCommaFormatter());
        $request = new PreparedRequest(HttpMethod::POST, 'https://api.test');

        $result = $auth->authenticate($request);

        expect($result->headers['Authorization'] ?? null)
            ->toBe('Signature key="id", note="a\"b\\\\c", flag="true"');
    });

    it('объединяет параметры из provider и переопределяет базовые', function () {
        $provider = new SignatureParamsProvider('secret', 100);
        $auth = new AuthorizationSchemeAuthenticator('Signature', params: [
            'key' => 'id',
            'ts' => 1,
        ], provider: $provider, formatter: new QuotedCommaFormatter());
        $request = new PreparedRequest(HttpMethod::GET, 'https://api.test');

        $result = $auth->authenticate($request);
        $signature = hash_hmac('sha256', $request->url . 100, 'secret');

        expect($result->headers['Authorization'] ?? null)
            ->toBe('Signature key="id", ts="100", sign="' . $signature . '"');
    });

    it('собирает заголовок в формате query-like', function () {
        $auth = new AuthorizationSchemeAuthenticator('ReestroAuth', params: [
            'apiKey' => 'key',
            'portal.orgid' => 'org',
        ], formatter: new QueryLikeFormatter());
        $request = new PreparedRequest(HttpMethod::GET, 'https://api.test');

        $result = $auth->authenticate($request);

        expect($result->headers['Authorization'] ?? null)
            ->toBe('ReestroAuth apiKey=key&portal.orgid=org');
    });
});
