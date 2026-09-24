<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use Composer\Autoload\ClassLoader;
use RuntimeException;

final readonly class DiscoveryTestHelper
{
    /**
     * @return array<int, ClassLoader>
     */
    public static function composerLoaders(): array
    {
        $loaders = [];

        foreach (spl_autoload_functions() as $loader) {
            if (!is_array($loader)) {
                continue;
            }

            $instance = $loader[0] ?? null;
            if ($instance instanceof ClassLoader) {
                $loaders[] = $instance;
            }
        }

        return $loaders;
    }

    public static function composerLoaderOrFail(): ClassLoader
    {
        foreach (self::composerLoaders() as $loader) {
            return $loader;
        }

        throw new RuntimeException('Не удалось найти Composer ClassLoader.');
    }

    public static function ensurePsr4Registered(string $prefix, string $path): void
    {
        $normalizedPath = self::normalizePath($path);
        $loader = self::composerLoaderOrFail();
        $registered = $loader->getPrefixesPsr4()[$prefix] ?? [];
        $normalizedRegistered = array_map(
            static fn (string $item): string => self::normalizePath($item),
            $registered,
        );

        if (!in_array($normalizedPath, $normalizedRegistered, true)) {
            $loader->addPsr4($prefix, $path);
        }
    }

    private static function normalizePath(string $path): string
    {
        $resolved = realpath($path);

        return $resolved !== false ? $resolved : $path;
    }
}
