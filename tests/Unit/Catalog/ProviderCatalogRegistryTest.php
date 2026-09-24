<?php

declare(strict_types=1);

use ApiSutra\Catalog\ProviderCatalogMeta;
use ApiSutra\Catalog\ProviderCatalogRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Catalog\ProviderCatalogInterface;
use ApiSutra\Contracts\Interfaces\Catalog\ProviderCatalogMetaInterface;
use ApiSutra\Contracts\Interfaces\Catalog\RequestBoundProviderCatalogInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Requests\SerializationRequest;
use ApiSutra\Transport\MockTransport;

describe('Provider catalog registry', function () {
    it('хранит мету каталога', function () {
        $generatedAt = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $meta = new ProviderCatalogMeta(
            generatedAt: $generatedAt,
            source: 'docs',
            sourceVersion: 'v1',
        );

        expect($meta->generatedAt())->toBe($generatedAt)
            ->and($meta->source())->toBe('docs')
            ->and($meta->sourceVersion())->toBe('v1');
    });

    it('резолвит catalog по ключу и возвращает все каталоги', function () {
        $pricing = makeProviderCatalog('pricing');
        $operations = makeProviderCatalog('operations');

        $registry = new ProviderCatalogRegistry([$pricing, $operations]);

        expect($registry->has('pricing'))->toBeTrue()
            ->and($registry->get('pricing'))->toBe($pricing)
            ->and(array_keys($registry->all()))->toBe(['pricing', 'operations']);
    });

    it('фильтрует request-bound каталоги по классу запроса', function () {
        $requestClass = SerializationRequest::class;
        $pricing = makeProviderCatalog('pricing');
        $requestBound = makeRequestBoundCatalog('operations', [$requestClass]);

        $registry = new ProviderCatalogRegistry([$pricing, $requestBound]);

        expect(array_keys($registry->forRequest($requestClass)))->toBe(['operations'])
            ->and(array_keys($registry->forRequest('App\\UnknownRequest')))->toBe([]);
    });

    it('не допускает пустой ключ catalog', function () {
        expect(fn () => new ProviderCatalogRegistry([
            makeProviderCatalog(' '),
        ]))->toThrow(ConfigurationException::class, 'Catalog key must not be empty');
    });

    it('не допускает дублирующийся ключ catalog', function () {
        expect(fn () => new ProviderCatalogRegistry([
            makeProviderCatalog('pricing'),
            makeProviderCatalog('pricing'),
        ]))->toThrow(ConfigurationException::class, 'A catalog is already registered for key: pricing');
    });

    it('ClientConfig::with сохраняет и переопределяет registry', function () {
        $first = new ProviderCatalogRegistry([makeProviderCatalog('pricing')]);
        $second = new ProviderCatalogRegistry([makeProviderCatalog('operations')]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            providerCatalogRegistry: $first,
        );

        $updated = $config->with(providerCatalogRegistry: $second);

        expect($config->providerCatalogRegistry)->toBe($first)
            ->and($updated->providerCatalogRegistry)->toBe($second);
    });

    it('AbstractClient отдает catalog accessors и сохраняет backward-compatible null', function () {
        $registry = new ProviderCatalogRegistry([
            makeProviderCatalog('pricing'),
            makeRequestBoundCatalog('operations', [SerializationRequest::class]),
        ]);

        $clientWithRegistry = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                providerCatalogRegistry: $registry,
            ),
            new MockTransport(),
        );

        $clientWithoutRegistry = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test'),
            new MockTransport(),
        );

        expect($clientWithRegistry->providerCatalogs())->toBe($registry)
            ->and($clientWithRegistry->providerCatalog('pricing')?->key())->toBe('pricing')
            ->and(array_keys($clientWithRegistry->providerCatalogsForRequest(SerializationRequest::class)))->toBe(['operations'])
            ->and($clientWithoutRegistry->providerCatalogs())->toBeNull()
            ->and($clientWithoutRegistry->providerCatalog('pricing'))->toBeNull()
            ->and($clientWithoutRegistry->providerCatalogsForRequest(SerializationRequest::class))->toBe([]);
    });
});

function makeProviderCatalog(string $key): ProviderCatalogInterface
{
    return new readonly class ($key) implements ProviderCatalogInterface {
        public function __construct(
            private string $key,
        ) {
        }

        #[\Override]
        public function key(): string
        {
            return $this->key;
        }

        #[\Override]
        public function meta(): ProviderCatalogMetaInterface
        {
            return new ProviderCatalogMeta(
                generatedAt: new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
                source: 'test',
                sourceVersion: 'v1',
            );
        }
    };
}

/**
 * @param array<int, string> $requestClasses
 */
function makeRequestBoundCatalog(string $key, array $requestClasses): RequestBoundProviderCatalogInterface
{
    return new readonly class ($key, $requestClasses) implements RequestBoundProviderCatalogInterface {
        /**
         * @param array<int, string> $requestClasses
         */
        public function __construct(
            private string $key,
            private array $requestClasses,
        ) {
        }

        #[\Override]
        public function key(): string
        {
            return $this->key;
        }

        #[\Override]
        public function meta(): ProviderCatalogMetaInterface
        {
            return new ProviderCatalogMeta(
                generatedAt: new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
                source: 'test',
                sourceVersion: 'v1',
            );
        }

        #[\Override]
        public function supportsRequest(string $requestClass): bool
        {
            return in_array($requestClass, $this->requestClasses, true);
        }
    };
}
