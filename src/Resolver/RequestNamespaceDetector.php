<?php

declare(strict_types=1);

namespace ApiSutra\Resolver;

use ApiSutra\Contracts\Interfaces\Localization\LocalizableExceptionInterface;
use ApiSutra\Localization\Message;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

/**
 * Определяет namespace запросов, принадлежащих клиенту.
 *
 * Делает root‑scan по классу клиента, а при отсутствии совпадений
 * использует fallback‑конвенции.
 */
final readonly class RequestNamespaceDetector
{
    public function __construct(
        private RequestScanner $scanner,
        private LocalizationConfig $localization = new LocalizationConfig(),
    ) {
    }

    /**
     * Обнаружить namespace запросов клиента.
     *
     * @return array<int, string>
     */
    public function detect(ClientInterface $client): array
    {
        try {
            $root = $this->inferRootNamespace($client::class);
            $conventions = $this->conventionalNamespaces($root);

            $classes = $this->scanner->scanRoot($root);
            if ($classes === []) {
                $classes = $this->scanner->scanNamespaces($conventions);
            }

            if ($classes === []) {
                $clientClass = $client::class;
                throw new ConfigurationException(new Message('resolver.no_requests_found_for_client', ['clientClass' => $clientClass]));
            }

            $namespaces = [];
            foreach ($classes as $class) {
                $pos = strrpos($class, '\\');
                if ($pos !== false) {
                    $namespaces[] = substr($class, 0, $pos);
                }
            }

            return array_values(array_unique($namespaces));
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->localization);
        }
    }

    /**
     * Вычислить root‑namespace по имени класса клиента.
     *
     * По умолчанию берутся первые два сегмента: Vendor\\Package.
     */
    private function inferRootNamespace(string $clientClass): string
    {
        $parts = explode('\\', trim($clientClass, '\\'));
        if (count($parts) < 2) {
            return $clientClass;
        }

        return implode('\\', array_slice($parts, 0, 2));
    }

    /**
     * Конвенциональные namespace запросов.
     *
     * @return array<int, string>
     */
    private function conventionalNamespaces(string $root): array
    {
        return [
            $root . '\\Requests',
            $root . '\\Resources',
        ];
    }
}
