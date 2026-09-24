<?php

declare(strict_types=1);

use ApiSutra\Request\PaginationOptions;

describe('PaginationOptions', function () {
    it('по умолчанию не содержит значений', function () {
        $options = PaginationOptions::empty();

        expect($options->hasPage())->toBeFalse();
        expect($options->hasLimit())->toBeFalse();
        expect($options->hasCursor())->toBeFalse();
        expect($options->getPage())->toBeNull();
        expect($options->getLimit())->toBeNull();
        expect($options->getCursor())->toBeNull();
    });

    it('withPage не мутирует исходный объект', function () {
        $options = PaginationOptions::empty();
        $updated = $options->withPage(2);

        expect($options->hasPage())->toBeFalse();
        expect($updated->hasPage())->toBeTrue();
        expect($updated->getPage())->toBe(2);
    });

    it('withLimit и withCursor сохраняют другие значения', function () {
        $options = PaginationOptions::empty()->withPage(3);
        $updated = $options->withLimit(50)->withCursor('next');

        expect($updated->getPage())->toBe(3);
        expect($updated->getLimit())->toBe(50);
        expect($updated->getCursor())->toBe('next');
    });
});
