<?php

declare(strict_types=1);

use ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Support\ContainerProviderRegistry;
use ApiSutra\Support\NullContainerProvider;
use ApiSutra\Support\TransportResolver;
use ApiSutra\Tests\Support\FakeTransport;

beforeEach(function () {
    ContainerProviderRegistry::set(new NullContainerProvider());
});

describe('TransportResolver', function () {
    it('возвращает явно переданный transport', function () {
        $explicit = new FakeTransport();

        $resolved = TransportResolver::resolve($explicit);

        expect($resolved)->toBe($explicit);
    });

    it('резолвит transport из контейнера', function () {
        $transport = new FakeTransport();
        $provider = new class ($transport) implements ContainerProviderInterface {
            public function __construct(
                private readonly TransportInterface $transport,
            ) {
            }

            #[\Override]
            public function bound(string $id): bool
            {
                return $id === TransportInterface::class;
            }

            #[\Override]
            public function make(string $id): ?object
            {
                return $id === TransportInterface::class ? $this->transport : null;
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

        $resolved = TransportResolver::resolve(null, $provider);

        expect($resolved)->toBe($transport);
    });

    it('бросает исключение если TransportInterface не найден в контейнере', function () {
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

        expect(fn () => TransportResolver::resolve(null, $provider))
            ->toThrow(ConfigurationException::class, 'TransportInterface not found in the container. Pass transport explicitly.');
    });

    it('бросает исключение если контейнер вернул неверный тип transport', function () {
        $provider = new class implements ContainerProviderInterface {
            #[\Override]
            public function bound(string $id): bool
            {
                return $id === TransportInterface::class;
            }

            #[\Override]
            public function make(string $id): ?object
            {
                if ($id === TransportInterface::class) {
                    return new stdClass();
                }

                return null;
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

        expect(fn () => TransportResolver::resolve(null, $provider))
            ->toThrow(ConfigurationException::class, 'Container returned an invalid transport. Expected TransportInterface.');
    });
});
