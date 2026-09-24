<?php

declare(strict_types=1);

namespace ApiSutra\Support;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

final class TransportResolver
{
    public static function resolve(
        ?TransportInterface $transport = null,
        ?ContainerProviderInterface $containerProvider = null,
    ): TransportInterface {
        if ($transport instanceof TransportInterface) {
            return $transport;
        }

        $provider = ContainerProviderRegistry::resolve($containerProvider);
        if (!$provider->bound(TransportInterface::class)) {
            throw new ConfigurationException(
                new Message('support.transportinterface_not_found_in_the_container_pass_transport_explicitly'),
            );
        }

        $resolved = $provider->make(TransportInterface::class);
        if (!$resolved instanceof TransportInterface) {
            throw new ConfigurationException(
                new Message('support.container_returned_an_invalid_transport_expected_transportinterface'),
            );
        }

        return $resolved;
    }
}
