<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

// В установленном пакете Composer находится выше vendor/apisutra/php.
$autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    $autoload = dirname(__DIR__, 5) . '/autoload.php';
}
/** @var ClassLoader $loader */
$loader = require $autoload;
$loader->addPsr4('Example\\Records\\', __DIR__ . '/src/');
$loader->addPsr4('Example\\ResultErrors\\', __DIR__ . '/../result-errors/src/');
$loader->addPsr4('Example\\HydrationRules\\', __DIR__ . '/../hydration-rules/src/');
$loader->addPsr4('Example\\Continuation\\', __DIR__ . '/../continuation/src/');
$loader->addPsr4('Example\\DtoShowcase\\', __DIR__ . '/../dto-showcase/src/');
$loader->addPsr4('Example\\ClientShowcase\\', __DIR__ . '/../client-showcase/src/');
$loader->addPsr4('Example\\CustomResult\\', __DIR__ . '/../custom-result/src/');
$loader->addPsr4('Example\\ConstructorValues\\', __DIR__ . '/../constructor-values/src/');
$loader->addPsr4('Example\\Files\\', __DIR__ . '/../files/src/');

$loader->addPsr4('Example\\DtoHydrator\\', __DIR__ . '/../dto-hydrator/src/');
