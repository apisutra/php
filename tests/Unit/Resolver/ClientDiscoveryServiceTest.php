<?php

declare(strict_types=1);

use Acme\Discovery\DiscoveryClient;
use Acme\Discovery\Requests\DiscoveryRequest;
use Acme\Discovery\Resources\DiscoveryResourceRequest;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Resolver\ClassMapProvider;
use ApiSutra\Resolver\ClientDiscoveryCache;
use ApiSutra\Resolver\ClientDiscoveryService;
use ApiSutra\Resolver\ClientRegistry;
use ApiSutra\Resolver\DiscoveryOptions;
use ApiSutra\Resolver\RequestNamespaceDetector;
use ApiSutra\Resolver\RequestScanner;
use ApiSutra\Result\ResolvedResultInterface;
use ApiSutra\Result\ResultHandle;
use ApiSutra\Response\ClientResponse;
use ApiSutra\Tests\Support\SpyCache;
use Psr\SimpleCache\CacheInterface;
use ApiSutra\Enums\Discovery\DiscoveryCacheMode;
use ApiSutra\Tests\Stubs\Resolver\FixedCache;
use ApiSutra\Tests\Stubs\Resolver\NoScanClient;
use ApiSutra\Support\ContainerProviderRegistry;

function resolveComposerChecksumForTest(): string
{
    $root = getcwd();
    if (!is_string($root)) {
        return '';
    }

    $paths = [
        $root . DIRECTORY_SEPARATOR . 'composer.lock',
        $root . DIRECTORY_SEPARATOR . 'composer.json',
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            return sha1_file($path) ?: '';
        }
    }

    return '';
}

describe('ClientDiscoveryService', function () {
    it('использует кешированный список namespace', function () {
        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $cache = new ClientDiscoveryCache(new FixedCache(['Acme\\Cached\\Requests']));
        $service = new ClientDiscoveryService($registry, $detector, $cache);

        $client = new NoScanClient(
            new ClientConfig(baseUrl: 'https://example.test', environment: Environment::Local),
        );

        $service->registerAuto($client, DiscoveryOptions::forceOn());

        expect($registry->resolve('Acme\\Cached\\Requests\\AnyRequest'))->toBe($client);
    });

    it('кеширует результат при включенном кеше', function () {
        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $store = new SpyCache();
        $cache = new ClientDiscoveryCache($store);
        $service = new ClientDiscoveryService($registry, $detector, $cache);

        $client = new DiscoveryClient(
            new ClientConfig(baseUrl: 'https://example.test', environment: Environment::Local),
        );

        $service->registerAuto($client, DiscoveryOptions::forceOn(120));

        expect($registry->resolve(DiscoveryRequest::class))->toBe($client);
        expect($store->lastSetKey)->not->toBeNull();
        expect(str_starts_with((string) $store->lastSetKey, 'apisutra.discovery.'))->toBeTrue();
        expect($store->lastSetTtl)->toBe(120);
    });

    it('регистрирует несколько namespace при auto-discovery', function () {
        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $cache = new ClientDiscoveryCache();
        $service = new ClientDiscoveryService($registry, $detector, $cache);

        $client = new DiscoveryClient(
            new ClientConfig(baseUrl: 'https://example.test', environment: Environment::Local),
        );

        $service->registerAuto($client, DiscoveryOptions::forceOff());

        expect($registry->resolve(DiscoveryRequest::class))->toBe($client);
        expect($registry->resolve(DiscoveryResourceRequest::class))->toBe($client);
    });

    it('включает composer checksum в ключ кеша', function () {
        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $cache = new ClientDiscoveryCache();
        $service = new ClientDiscoveryService($registry, $detector, $cache);

        $options = new DiscoveryOptions(
            cacheMode: DiscoveryCacheMode::ForceOn,
            cacheKeyVersion: 'v1',
        );

        $method = new ReflectionMethod(ClientDiscoveryService::class, 'buildCacheKey');

        $key = $method->invoke($service, DiscoveryClient::class, $options);
        $checksum = resolveComposerChecksumForTest();
        $expected = hash('sha256', DiscoveryClient::class . '|v1|' . $checksum);

        expect($key)->toBe($expected);
    });

    it('resolveBasePath использует ContainerProvider', function () {
        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $cache = new ClientDiscoveryCache();
        $service = new ClientDiscoveryService($registry, $detector, $cache);

        $provider = new class implements ContainerProviderInterface {
            #[\Override]
            public function bound(string $id): bool
            {
                return false;
            }

            #[\Override]
            public function make(string $id): ?object
            {
                return null;
            }

            #[\Override]
            public function basePath(): ?string
            {
                return '/tmp/apisutra';
            }

            #[\Override]
            public function environment(): ?string
            {
                return null;
            }

            #[\Override]
            public function isDebug(): ?bool
            {
                return null;
            }

            #[\Override]
            public function validatorFactory(): ?object
            {
                return null;
            }
        };

        ContainerProviderRegistry::set($provider);

        $method = new ReflectionMethod(ClientDiscoveryService::class, 'resolveBasePath');

        $path = $method->invoke($service);

        expect($path)->toBe('/tmp/apisutra');
    });
});
