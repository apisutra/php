<?php

declare(strict_types=1);

use ApiSutra\Testing\FixtureRedactor;
use ApiSutra\Tests\Stubs\Fixtures\SensitiveFixture;

describe('FixtureRedactor', function () {
    it('маскирует заголовки, json параметры и regex в body', function () {
        $redactor = new FixtureRedactor();
        $fixture = new SensitiveFixture();

        $payload = [
            'request' => [
                'headers' => ['Authorization' => 'token'],
                'body' => ['token' => 'secret-token'],
            ],
            'response' => [
                'headers' => ['Authorization' => 'response-token'],
                'body' => 'secret=123',
            ],
        ];

        $redacted = $redactor->redact($payload, $fixture);

        expect($redacted['request']['headers']['Authorization'] ?? null)->toBe('***');
        expect($redacted['request']['body']['token'] ?? null)->toBe('***');
        expect($redacted['response']['body'] ?? null)->toBe('secret=***');
    });
});
