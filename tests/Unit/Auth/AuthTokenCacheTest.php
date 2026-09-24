<?php

declare(strict_types=1);

use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Pipeline\Auth\AuthTokenCache;
use ApiSutra\Tests\Support\StrictCache;
use ApiSutra\Tests\Support\VirtualClock;

it('bulk и delete изолированы, а clear не удаляет общий store', function (): void {
    $store = new StrictCache();
    $store->set('unrelated', 'keep');
    $a = new AuthTokenCache($store, 'a');
    $b = new AuthTokenCache($store, 'b');
    expect($a->setMultiple(['logical:one' => 'a', 'logical:two' => null], 60))->toBeTrue();
    $b->set('logical:one', 'b', 60);
    expect($a->getMultiple(['logical:one', 'logical:two']))->toBe(['logical:one' => 'a', 'logical:two' => null])
        ->and($a->has('logical:two'))->toBeTrue();
    expect(fn () => $a->clear())->toThrow(ConfigurationException::class);
    expect($a->deleteMultiple(['logical:one', 'logical:two']))->toBeTrue()
        ->and($a->has('logical:one'))->toBeFalse()
        ->and($b->get('logical:one'))->toBe('b')
        ->and($store->get('unrelated'))->toBe('keep');
});

it('локальная область поддерживает TTL без реального сна', function (): void {
    $clock = new VirtualClock();
    $cache = new AuthTokenCache(null, 'fixture', $clock);
    $cache->setMultiple(['one' => 'a', 'two' => 'b'], 1);
    $clock->advance(1000);
    expect($cache->getMultiple(['one', 'two'], 'miss'))->toBe(['one' => 'miss', 'two' => 'miss']);
    $cache->set('one', 'a');
    $cache->set('one', 'deleted', 0);
    expect($cache->has('one'))->toBeFalse();
});
