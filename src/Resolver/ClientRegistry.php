<?php

declare(strict_types=1);

namespace ApiSutra\Resolver;

use ApiSutra\Contracts\Interfaces\Localization\LocalizableExceptionInterface;
use ApiSutra\Localization\Message;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

/**
 * Реестр клиентов и привязок к namespace запросов.
 *
 * Хранит соответствие "namespace запроса → клиент" и гарантирует,
 * что запрос всегда попадёт к своему владельцу.
 */
final class ClientRegistry
{
    public function __construct(private readonly LocalizationConfig $localization = new LocalizationConfig())
    {
    }

    /**
     * @var array<string, ClientInterface>
     */
    private array $clients = [];

    /**
     * @var array<string, ClientInterface>
     */
    private array $resolved = [];

    /**
     * Зарегистрировать клиента по namespace запросов.
     *
     * По умолчанию namespace выводится из класса клиента (Root\\Requests).
     * При повторной регистрации одного namespace бросает исключение.
     */
    public function register(ClientInterface $client, ?string $requestNamespace = null, bool $force = false): void
    {
        try {
            $namespace = $this->normalizeNamespace(
                $requestNamespace ?? $this->inferRequestNamespace($client::class),
            );

            if (isset($this->clients[$namespace]) && !$force) {
                throw new ConfigurationException(new Message('resolver.namespace_is_already_registered_for_a_client', ['namespace' => $namespace]));
            }

            $this->clients[$namespace] = $client;
            $this->resolved = [];
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->localization);
        }
    }

    /**
     * Разрешить клиента по классу запроса.
     *
     * Используется самый длинный совпадающий namespace, чтобы корректно
     * работать при вложенных структурах.
     */
    public function resolve(string $requestClass): ClientInterface
    {
        try {
            if (isset($this->resolved[$requestClass])) {
                return $this->resolved[$requestClass];
            }

            $client = $this->matchByNamespace($requestClass);
            if ($client === null) {
                throw new ConfigurationException(new Message('resolver.no_client_registered_for_request', ['requestClass' => $requestClass]));
            }

            return $this->resolved[$requestClass] = $client;
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->localization);
        }
    }

    /**
     * Проверить, что запрос соответствует клиенту.
     *
     * Нужен для защиты от отправки запроса "чужого" клиента.
     */
    public function assertOwnership(ClientInterface $client, string $requestClass): void
    {
        try {
            $expected = $this->resolve($requestClass);
            if ($expected::class !== $client::class) {
                $expectedClass = $expected::class;
                $clientClass = $client::class;
                throw new ConfigurationException(
                    new Message('resolver.request_belongs_to_client_not', ['requestClass' => $requestClass, 'expectedClass' => $expectedClass, 'clientClass' => $clientClass]),
                );
            }
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->localization);
        }
    }

    private function matchByNamespace(string $requestClass): ?ClientInterface
    {
        $matched = null;
        $matchedLength = -1;

        foreach ($this->clients as $namespace => $client) {
            if ($requestClass === $namespace || str_starts_with($requestClass, $namespace . '\\')) {
                $length = strlen($namespace);
                if ($length > $matchedLength) {
                    $matched = $client;
                    $matchedLength = $length;
                }
            }
        }

        return $matched;
    }

    private function normalizeNamespace(string $namespace): string
    {
        return trim($namespace, '\\');
    }

    /**
     * По умолчанию ожидается namespace вида "Root\\Requests".
     */
    private function inferRequestNamespace(string $clientClass): string
    {
        $parts = explode('\\', trim($clientClass, '\\'));
        if (count($parts) < 2) {
            return $clientClass;
        }

        $root = implode('\\', array_slice($parts, 0, 2));
        return $root . '\\Requests';
    }
}
