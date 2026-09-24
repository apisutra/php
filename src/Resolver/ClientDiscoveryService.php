<?php

declare(strict_types=1);

namespace ApiSutra\Resolver;

use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Support\ContainerProviderRegistry;

/**
 * Оркестратор auto‑discovery.
 *
 * Отвечает за: детекцию namespace запросов, кеширование результата
 * и регистрацию клиента в ClientRegistry.
 */
final readonly class ClientDiscoveryService
{
    public function __construct(
        private ClientRegistry $registry,
        private RequestNamespaceDetector $detector,
        private ClientDiscoveryCache $cache,
    ) {
    }

    /**
     * Зарегистрировать клиента на основе auto‑discovery.
     *
     * Логика:
     * 1) определить режим кеша по окружению/опциям;
     * 2) попытаться взять namespace из кеша;
     * 3) при промахе — выполнить детекцию и сохранить результат;
     * 4) зарегистрировать все найденные namespace в реестре.
     */
    public function registerAuto(ClientInterface $client, ?DiscoveryOptions $options = null): void
    {
        $options ??= DiscoveryOptions::auto();
        $environment = $client->getConfig()->environment ?? Environment::Production;
        $cacheEnabled = $options->isCacheEnabled($environment);
        $cacheKey = $this->buildCacheKey($client::class, $options);

        if ($cacheEnabled) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached) && $cached !== []) {
                $this->registerNamespaces($client, $cached);
                return;
            }
        }

        $namespaces = $this->detector->detect($client);

        if ($cacheEnabled) {
            $this->cache->put($cacheKey, $namespaces, $options->cacheTtl);
        }

        $this->registerNamespaces($client, $namespaces);
    }

    /**
     * Зарегистрировать все namespace для клиента.
     *
     * @param array<int, string> $namespaces
     */
    private function registerNamespaces(ClientInterface $client, array $namespaces): void
    {
        foreach ($namespaces as $namespace) {
            $this->registry->register($client, $namespace);
        }
    }

    /**
     * Сформировать стабильный ключ кеша.
     *
     * Включает: класс клиента + ручную версию + checksum composer.
     */
    private function buildCacheKey(string $clientClass, DiscoveryOptions $options): string
    {
        $parts = [
            $clientClass,
            $options->cacheKeyVersion ?? '',
            $this->resolveComposerChecksum(),
        ];

        return hash('sha256', implode('|', $parts));
    }

    /**
     * Получить checksum composer.json/lock для авто‑инвалидации.
     *
     * Если файлов нет — возвращает пустую строку (ключ всё ещё стабилен).
     */
    private function resolveComposerChecksum(): string
    {
        static $checksum = null;
        if (is_string($checksum)) {
            return $checksum;
        }

        $root = $this->resolveBasePath();
        $paths = [
            $root !== null ? $root . DIRECTORY_SEPARATOR . 'composer.lock' : null,
            $root !== null ? $root . DIRECTORY_SEPARATOR . 'composer.json' : null,
        ];

        foreach ($paths as $path) {
            if (is_string($path) && is_file($path)) {
                $checksum = sha1_file($path) ?: '';
                return $checksum;
            }
        }

        $checksum = '';
        return $checksum;
    }

    /**
     * Базовый путь приложения для поиска composer.*.
     */
    private function resolveBasePath(): ?string
    {
        $provider = ContainerProviderRegistry::resolve();
        $base = $provider->basePath();
        if (is_string($base)) {
            return $base;
        }

        $cwd = getcwd();
        return is_string($cwd) ? $cwd : null;
    }
}
