<?php

declare(strict_types=1);

namespace ApiSutra\Resolver;

use ApiSutra\Core\AbstractRequest;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Сканер классов запросов.
 *
 * Стратегия: если доступен composer classmap — используем его (быстро и дёшево),
 * иначе обходим PSR‑4 директории по namespace и проверяем классы через autoload.
 */
final readonly class RequestScanner
{
    public function __construct(
        private ClassMapProvider $classMapProvider,
    ) {
    }

    /**
     * Найти классы запросов внутри заданных namespace.
     *
     * @param array<int, string> $namespaces
     * @return array<int, string>
     */
    public function scanNamespaces(array $namespaces): array
    {
        if ($namespaces === []) {
            return [];
        }

        $classMap = $this->classMapProvider->getClassMap();
        if ($classMap !== []) {
            $classes = array_keys($classMap);
            $matched = $this->filterRequestClasses($classes, $namespaces);
            if ($matched !== []) {
                return $matched;
            }
        }

        return $this->scanPsr4Namespaces($namespaces);
    }

    /**
     * Найти классы запросов в пределах root‑namespace клиента.
     *
     * Используется как основной путь, чтобы поддержать любые структуры
     * папок внутри пакета клиента.
     *
     * @return array<int, string>
     */
    public function scanRoot(string $rootNamespace): array
    {
        $classMap = $this->classMapProvider->getClassMap();
        if ($classMap !== []) {
            $classes = array_keys($classMap);
            $matched = $this->filterRequestClasses($classes, [$rootNamespace]);
            if ($matched !== []) {
                return $matched;
            }
        }

        return $this->scanPsr4Namespaces([$rootNamespace]);
    }

    /**
     * Отфильтровать список классов по namespace и типу запроса.
     *
     * @param array<int, string> $classes
     * @param array<int, string> $namespaces
     * @return array<int, string>
     */
    private function filterRequestClasses(array $classes, array $namespaces): array
    {
        $result = [];
        foreach ($classes as $class) {
            if (!$this->matchesNamespaces($class, $namespaces)) {
                continue;
            }

            if ($this->isRequestClass($class)) {
                $result[] = $class;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * Сканировать PSR‑4 директории и собрать классы запросов.
     *
     * @param array<int, string> $namespaces
     * @return array<int, string>
     */
    private function scanPsr4Namespaces(array $namespaces): array
    {
        $prefixes = $this->classMapProvider->getPsr4Prefixes();
        $classes = [];

        foreach ($namespaces as $namespace) {
            [$prefix, $dirs] = $this->resolvePrefix($prefixes, $namespace);
            if ($prefix === null || $dirs === []) {
                continue;
            }

            $subPath = ltrim(substr($namespace, strlen($prefix)), '\\');
            $relativePath = $subPath !== '' ? str_replace('\\', DIRECTORY_SEPARATOR, $subPath) : '';

            foreach ($dirs as $dir) {
                $root = rtrim($dir, DIRECTORY_SEPARATOR);
                $scanRoot = $relativePath === '' ? $root : $root . DIRECTORY_SEPARATOR . $relativePath;
                $classes = array_merge($classes, $this->scanDirectory($scanRoot, $namespace));
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * Подобрать наиболее специфичный PSR‑4 префикс для namespace.
     *
     * @param array<string, array<int, string>> $prefixes
     * @return array{0:?string,1:array<int, string>}
     */
    private function resolvePrefix(array $prefixes, string $namespace): array
    {
        $matchedPrefix = null;
        $matchedDirs = [];

        foreach ($prefixes as $prefix => $dirs) {
            $normalizedPrefix = rtrim($prefix, '\\');
            if (
                $namespace !== $normalizedPrefix
                && !str_starts_with($namespace, $normalizedPrefix . '\\')
            ) {
                continue;
            }

            if ($matchedPrefix === null || strlen($normalizedPrefix) > strlen($matchedPrefix)) {
                $matchedPrefix = $normalizedPrefix;
                $matchedDirs = $dirs;
            }
        }

        return [$matchedPrefix, $matchedDirs];
    }

    /**
     * Просканировать директорию и построить FQCN по относительным путям.
     *
     * @return array<int, string>
     */
    private function scanDirectory(string $root, string $baseNamespace): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $classes = [];
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root));
            $relative = ltrim($relative, DIRECTORY_SEPARATOR);
            $class = $baseNamespace . '\\' . str_replace(
                DIRECTORY_SEPARATOR,
                '\\',
                substr($relative, 0, -4),
            );

            if ($this->isRequestClass($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * Проверить, входит ли класс в один из namespace.
     *
     * @param array<int, string> $namespaces
     */
    private function matchesNamespaces(string $class, array $namespaces): bool
    {
        foreach ($namespaces as $namespace) {
            if ($class === $namespace || str_starts_with($class, $namespace . '\\')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Является ли класс запросом.
     *
     * Важно: class_exists триггерит autoload, поэтому метод может быть дорогим
     * при большом количестве файлов — именно поэтому в проде предпочтителен classmap.
     */
    private function isRequestClass(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        return is_subclass_of($class, AbstractRequest::class);
    }
}
