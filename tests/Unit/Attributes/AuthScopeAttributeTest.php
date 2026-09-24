<?php

declare(strict_types=1);

use ApiSutra\Attributes\Request\AuthScope;
use ApiSutra\Tests\Stubs\Enums\AuthScopeKey;
use ApiSutra\Tests\Stubs\Enums\NumericAuthScopeKey;

describe('AuthScope attribute', function () {
    it('поддерживает строковый scope как раньше', function () {
        $attribute = new AuthScope('system');

        expect($attribute->scope)->toBe('system');
    });

    it('нормализует string-backed enum case в строку scope', function () {
        $attribute = new AuthScope(AuthScopeKey::System);

        expect($attribute->scope)->toBe('system');
    });

    it('падает на non-string backed enum', function () {
        expect(fn () => new AuthScope(NumericAuthScopeKey::System))
            ->toThrow(\InvalidArgumentException::class, 'AuthScope supports only a string-backed enum or a string');
    });
});
