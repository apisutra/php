<?php

declare(strict_types=1);

use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Enums\Pagination\PaginationMode;
use ApiSutra\Pagination\PaginationRule;

describe('PaginationRule', function () {
    it('создаёт single', function () {
        $rule = PaginationRule::single();

        expect($rule->mode)->toBe(PaginationMode::Single)
            ->and($rule->isSingle())->toBeTrue()
            ->and($rule->pages)->toBeNull()
            ->and($rule->from)->toBeNull()
            ->and($rule->to)->toBeNull()
            ->and($rule->failStrategy)->toBe(FailStrategy::FailAll);
    });

    it('создаёт all с failStrategy', function () {
        $rule = PaginationRule::all(FailStrategy::Partial);

        expect($rule->mode)->toBe(PaginationMode::All)
            ->and($rule->failStrategy)->toBe(FailStrategy::Partial);
    });

    it('создаёт pages', function () {
        $rule = PaginationRule::pages(3);

        expect($rule->mode)->toBe(PaginationMode::Pages)
            ->and($rule->pages)->toBe(3)
            ->and($rule->failStrategy)->toBe(FailStrategy::FailAll);
    });

    it('создаёт range', function () {
        $rule = PaginationRule::range(2, 4, FailStrategy::IgnoreErrors);

        expect($rule->mode)->toBe(PaginationMode::Range)
            ->and($rule->from)->toBe(2)
            ->and($rule->to)->toBe(4)
            ->and($rule->failStrategy)->toBe(FailStrategy::IgnoreErrors);
    });
});
