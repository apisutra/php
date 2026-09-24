<?php

declare(strict_types=1);

use Acme\Discovery\Requests\DiscoveryRequest;
use ApiSutra\Pagination\AbstractPaginatedRequest;
use ApiSutra\Resolver\ClassMapProvider;
use ApiSutra\Resolver\RequestScanner;
use ApiSutra\Tests\Support\DiscoveryTestHelper;

describe('RequestScanner', function () {
    it('находит запросы через PSR-4, если classmap не даёт совпадений', function () {
        $scanner = new RequestScanner(new ClassMapProvider());

        $classes = $scanner->scanNamespaces(['ApiSutra\\Pagination']);

        expect($classes)->toContain(AbstractPaginatedRequest::class);
    });

    it('находит запросы через classmap при совпадениях', function () {
        $scanner = new RequestScanner(new ClassMapProvider());
        $loader = DiscoveryTestHelper::composerLoaderOrFail();

        $loader?->addClassMap([
            DiscoveryRequest::class => __DIR__ . '/../../Fixtures/Acme/Discovery/Requests/DiscoveryRequest.php',
        ]);

        $classes = $scanner->scanNamespaces(['Acme\\Discovery\\Requests']);

        expect($classes)->toContain(DiscoveryRequest::class);
    });
});
