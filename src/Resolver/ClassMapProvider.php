<?php

declare(strict_types=1);

namespace ApiSutra\Resolver;

use Composer\Autoload\ClassLoader;

/**
 * Доступ к данным Composer autoload.
 *
 * Позволяет получить classmap и PSR‑4 префиксы без прямого доступа
 * к файлам composer.json/lock, что ускоряет discovery в проде.
 */
final readonly class ClassMapProvider
{
    /**
     * @param array<string, string>|null $classMapOverride
     * @param array<string, array<int, string>>|null $psr4PrefixesOverride
     */
    public function __construct(
        private ?array $classMapOverride = null,
        private ?array $psr4PrefixesOverride = null,
    ) {
    }

    /**
     * Карта классов (FQCN => путь файла), если доступна.
     *
     * @return array<string, string>
     */
    public function getClassMap(): array
    {
        if (is_array($this->classMapOverride)) {
            return $this->classMapOverride;
        }

        $loader = $this->resolveLoader();
        return $loader?->getClassMap() ?? [];
    }

    /**
     * PSR‑4 префиксы (namespace => список директорий).
     *
     * @return array<string, array<int, string>>
     */
    public function getPsr4Prefixes(): array
    {
        if (is_array($this->psr4PrefixesOverride)) {
            return $this->psr4PrefixesOverride;
        }

        $loader = $this->resolveLoader();
        return $loader?->getPrefixesPsr4() ?? [];
    }

    /**
     * Найти активный Composer ClassLoader через autoload функции.
     *
     * Может вернуть null, если код выполняется вне композера
     * или загрузчик ещё не инициализирован.
     */
    private function resolveLoader(): ?ClassLoader
    {
        foreach (spl_autoload_functions() as $loader) {
            if (!is_array($loader)) {
                continue;
            }

            $instance = $loader[0] ?? null;
            if ($instance instanceof ClassLoader) {
                return $instance;
            }
        }

        return null;
    }
}
