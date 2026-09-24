<?php

declare(strict_types=1);

namespace ApiSutra\Resolver;

use ApiSutra\Contracts\Interfaces\Localization\LocalizableExceptionInterface;
use ApiSutra\Localization\Message;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Resolver\RequestNamespaceProviderInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use WeakMap;

final readonly class ServiceRegistrar
{
    /** @var WeakMap<ClientInterface, array{complete: bool, namespaces: array<string, true>}> */
    private WeakMap $registrations;

    public function __construct(
        private ClientRegistry $registry,
        private RequestNamespaceDetector $detector,
        private LocalizationConfig $localization = new LocalizationConfig(),
    ) {
        $this->registrations = new WeakMap();
    }

    /**
     * Зарегистрировать набор сервис‑клиентов в реестре.
     *
     * @param array<int, ClientInterface> $clients
     */
    public function register(array $clients): void
    {
        try {
            foreach ($clients as $client) {
                if (!$client instanceof ClientInterface) {
                    throw new ConfigurationException(new Message('resolver.expected_clientinterface_in_the_services_list'));
                }

                $state = $this->registrations[$client] ?? ['complete' => false, 'namespaces' => []];
                if ($state['complete']) {
                    continue;
                }

                $namespaces = $client instanceof RequestNamespaceProviderInterface
                    ? $client->requestNamespaces()
                    : $this->detector->detect($client);

                if ($namespaces === []) {
                    $class = $client::class;
                    throw new ConfigurationException(new Message('configuration.request_namespaces_missing', ['class' => $class]));
                }

                foreach ($namespaces as $namespace) {
                    $namespace = trim($namespace, '\\');
                    if (isset($state['namespaces'][$namespace])) {
                        continue;
                    }

                    $this->registry->register($client, $namespace);
                    // После ошибки сохраняем только привязки, которые действительно выполнены.
                    $state['namespaces'][$namespace] = true;
                    $this->registrations[$client] = $state;
                }

                $state['complete'] = true;
                $this->registrations[$client] = $state;
            }
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->localization);
        }
    }
}
