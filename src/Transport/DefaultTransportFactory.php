<?php

declare(strict_types=1);

namespace ApiSutra\Transport;

use ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory as GuzzleHttpFactory;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface as PsrRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface as PsrStreamFactoryInterface;

/**
 * Сборка PSR-транспорта из контейнера с запасным Guzzle-клиентом.
 */
final class DefaultTransportFactory
{
    public function create(ContainerProviderInterface $app): TransportInterface
    {
        $client = $this->resolvePsrClient($app);
        $defaultFactory = $this->resolveDefaultPsrFactory();
        $requestFactory = $this->resolvePsrRequestFactory($app) ?? $defaultFactory;
        $streamFactory = $this->resolvePsrStreamFactory($app) ?? $defaultFactory;

        if (
            $client instanceof PsrClientInterface
            && $requestFactory instanceof PsrRequestFactoryInterface
            && $streamFactory instanceof PsrStreamFactoryInterface
        ) {
            return new HttpTransport($client, $requestFactory, $streamFactory);
        }

        $guzzle = $this->resolveGuzzleClient();
        if ($guzzle instanceof PsrClientInterface) {
            $factory = $defaultFactory ?? new GuzzleHttpFactory();
            return new HttpTransport($guzzle, $factory, $factory);
        }

        throw new ConfigurationException(
            new Message('transport.transport_is_not_configured_bind_transportinterface_in_the_container'),
        );
    }

    private function resolvePsrClient(ContainerProviderInterface $app): ?PsrClientInterface
    {
        if (!$app->bound(PsrClientInterface::class)) {
            return null;
        }

        $client = $app->make(PsrClientInterface::class);
        if (!$client instanceof PsrClientInterface) {
            throw new ConfigurationException(new Message('transport.psr_18_client_binding_must_implement_clientinterface'));
        }
        return $client;
    }

    private function resolvePsrRequestFactory(ContainerProviderInterface $app): ?PsrRequestFactoryInterface
    {
        if (!$app->bound(PsrRequestFactoryInterface::class)) {
            return null;
        }

        $factory = $app->make(PsrRequestFactoryInterface::class);
        if (!$factory instanceof PsrRequestFactoryInterface) {
            throw new ConfigurationException(new Message('transport.psr_17_request_factory_binding_has_an_invalid_type'));
        }
        return $factory;
    }

    private function resolvePsrStreamFactory(ContainerProviderInterface $app): ?PsrStreamFactoryInterface
    {
        if (!$app->bound(PsrStreamFactoryInterface::class)) {
            return null;
        }

        $factory = $app->make(PsrStreamFactoryInterface::class);
        if (!$factory instanceof PsrStreamFactoryInterface) {
            throw new ConfigurationException(new Message('transport.psr_17_stream_factory_binding_has_an_invalid_type'));
        }
        return $factory;
    }

    private function resolveDefaultPsrFactory(): ?GuzzleHttpFactory
    {
        if (!class_exists(GuzzleHttpFactory::class)) {
            return null;
        }

        return new GuzzleHttpFactory();
    }

    private function resolveGuzzleClient(): ?PsrClientInterface
    {
        if (!class_exists(GuzzleClient::class)) {
            return null;
        }

        return new GuzzleHttpClient();
    }
}
