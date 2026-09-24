<?php

declare(strict_types=1);

use ApiSutra\Diagnostics\RedactionPolicy;

it('маскирует URL без потери повторяющихся и кодированных query', function (): void {
    $policy = new RedactionPolicy(fields: ['provider_key']);
    $url = 'https://user:password@example.test/path?access_token=first&access%5Ftoken=second&provider_key=third&filter%5Bpassword%5D=fourth&sort=a&sort=b&safe=%2F+%20#anchor';
    $safe = $policy->url($url);
    expect($safe)->toBe('https://***@example.test/path?access_token=***&access%5Ftoken=***&provider_key=***&filter%5Bpassword%5D=***&sort=a&sort=b&safe=%2F+%20#anchor');
});

it('маскирует настроенные пути и заголовки без изменения соседних полей', function (): void {
    $policy = new RedactionPolicy(headers: ['X-Provider-Credential'], paths: ['items.*.credential']);
    $payload = ['items' => [['credential' => 'secret', 'name' => 'visible']], 'credential' => 'public'];
    expect($policy->data($payload))->toBe(['items' => [['credential' => '***', 'name' => 'visible']], 'credential' => 'public'])
        ->and($payload['items'][0]['credential'])->toBe('secret')
        ->and($policy->headers(['x-provider-credential' => 'secret', 'Set-Cookie' => ['a=secret', 'b=secret']]))
        ->toBe(['x-provider-credential' => '***', 'Set-Cookie' => ['***', '***']]);
});

it('не экспортирует неразбираемый JSON и маскирует form body', function (): void {
    $policy = new RedactionPolicy();
    expect($policy->body('{"token":"secret",', 'application/json'))->toBe('[redacted-body]')
        ->and($policy->body('token=first&token=second&name=visible', 'application/x-www-form-urlencoded'))
        ->toBe('token=***&token=***&name=visible')
        ->and($policy->body('{"items":[{"password":"secret","id":1}]}'))
        ->toBe('{"items":[{"password":"***","id":1}]}');
});
