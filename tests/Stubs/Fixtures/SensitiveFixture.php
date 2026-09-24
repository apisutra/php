<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Fixtures;

use ApiSutra\Testing\Fixture;

final class SensitiveFixture extends Fixture
{
    protected function defineSensitiveHeaders(): array
    {
        return [
            'Authorization' => '***',
        ];
    }

    protected function defineSensitiveJsonParameters(): array
    {
        return [
            'token' => '***',
        ];
    }

    protected function defineSensitiveRegexPatterns(): array
    {
        return [
            '/secret=\\w+/' => 'secret=***',
        ];
    }
}
