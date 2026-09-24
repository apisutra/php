<?php

declare(strict_types=1);

use ApiSutra\Resolver\ClassMapProvider;
use Composer\Autoload\ClassLoader;

describe('ClassMapProvider', function () {
    it('возвращает пустые данные без Composer ClassLoader', function () {
        $loaders = spl_autoload_functions() ?: [];
        $removed = [];

        foreach ($loaders as $loader) {
            if (!is_array($loader)) {
                continue;
            }

            $instance = $loader[0] ?? null;
            if ($instance instanceof ClassLoader) {
                $removed[] = $loader;
                spl_autoload_unregister($loader);
            }
        }

        try {
            $provider = new ClassMapProvider();

            expect($provider->getClassMap())->toBe([])
                ->and($provider->getPsr4Prefixes())->toBe([]);
        } finally {
            foreach ($removed as $loader) {
                spl_autoload_register($loader);
            }
        }
    });
});
