<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Core\ClientResolverInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Support\ContainerProviderRegistry;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

describe('AbstractRequest резолвит клиента через ContainerProvider', function () {
    it('подхватывает ClientResolver из провайдера', function () {
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            environment: Environment::Testing,
        );
        $client = new TestClient($config, new MockTransport());

        $resolver = new class($client) implements ClientResolverInterface {
            public function __construct(
                private ClientInterface $client,
            ) {}

            #[\Override]
            public function resolve(RequestInterface $request): ClientInterface
            {
                return $this->client;
            }

            #[\Override]
            public function assertOwnership(ClientInterface $client, RequestInterface $request): void
            {
            }
        };

        $provider = new class($resolver) implements ContainerProviderInterface {
            public function __construct(
                private ClientResolverInterface $resolver,
            ) {}

            #[\Override]
            public function bound(string $id): bool
            {
                return $id === ClientResolverInterface::class;
            }

            #[\Override]
            public function make(string $id): ?object
            {
                return $id === ClientResolverInterface::class ? $this->resolver : null;
            }

            #[\Override]
            public function basePath(): ?string
            {
                return null;
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

        $request = new SimpleGetRequest('q');

        expect($request->getClient())->toBe($client);
    });
});
