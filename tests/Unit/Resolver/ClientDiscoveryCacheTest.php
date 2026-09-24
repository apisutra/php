<?php

declare(strict_types=1);

use ApiSutra\Resolver\ClientDiscoveryCache;
use ApiSutra\Tests\Support\SpyCache;

describe('ClientDiscoveryCache', function () {
    it('кеширует в памяти без стора', function () {
        $cache = new ClientDiscoveryCache();

        $cache->put('key', ['Acme\\Discovery\\Requests']);

        expect($cache->get('key'))->toBe(['Acme\\Discovery\\Requests']);
    });

    it('использует PSR-16 стор и префикс', function () {
        $store = new SpyCache();
        $cache = new ClientDiscoveryCache($store);

        $cache->put('key', ['Acme\\Discovery\\Requests'], 120);

        expect($store->lastSetKey)->toBe('apisutra.discovery.key');
        expect($store->lastSetTtl)->toBe(120);
        expect($cache->get('key'))->toBe(['Acme\\Discovery\\Requests']);
    });
});
