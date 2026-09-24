<?php

declare(strict_types=1);

$checkout = $argv[1] ?? dirname(__DIR__, 2);
$manifest = $argv[2] ?? __DIR__ . '/docs-api.json';
require $checkout . '/vendor/autoload.php';
$entries = json_decode((string) file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
$documents = [];
if (is_file($checkout . '/docs/translations.json')) {
    $translations = json_decode(
        (string) file_get_contents($checkout . '/docs/translations.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    foreach ($translations['pages'] as $page) {
        $documents[$page['id']] = array_values($page['paths']);
    }
}
$errors = [];
$methods = 0;
$parameters = 0;
foreach ($entries['declarations'] as $entry) {
    try {
        $class = new ReflectionClass($entry['class']);
        foreach ($entry['methods'] ?? [] as $name => $expected) {
            $method = $class->getMethod($name);
            ++$methods;
            if (!$method->isPublic()) {
                throw new RuntimeException($name . ' не public');
            }
            $actualReturn = (string) $method->getReturnType();
            // PHP 8.5 раскрывает self в имя объявляющего класса; static не нормализуем.
            $expectedReturn = $expected['return'];
            if ($expectedReturn === 'self' && $actualReturn === $method->getDeclaringClass()->getName()) {
                $actualReturn = 'self';
            }
            if ($actualReturn !== $expectedReturn) {
                throw new RuntimeException($name . ': изменён возвращаемый тип');
            }
            $actual = [];
            foreach ($method->getParameters() as $parameter) {
                ++$parameters;
                // Reflection не вычисляет значения default и аргументы атрибутов.
                $actualType = (string) $parameter->getType();
                $expectedType = $expected['parameters'][$parameter->getName()]['type'] ?? null;
                if ($expectedType === 'self' && $actualType === $method->getDeclaringClass()->getName()) {
                    $actualType = 'self';
                }
                $actual[$parameter->getName()] = [
                    'type' => $actualType,
                    'optional' => $parameter->isOptional(),
                    'variadic' => $parameter->isVariadic(),
                ];
            }
            if ($actual !== $expected['parameters']) {
                throw new RuntimeException($name . ': изменены имена, порядок или типы параметров');
            }
        }
    } catch (Throwable $error) {
        $errors[] = $entry['class'] . ': ' . $error->getMessage();
    }
    foreach ($entry['docs'] as $document) {
        $id = explode('#', $document)[0];
        foreach ($documents[$id] ?? [$id] as $path) {
            if (!is_file($checkout . '/' . $path)) {
                $errors[] = $document . ': не найден владелец декларации ' . $path;
            }
        }
    }
}
echo json_encode([
    'classes' => count($entries['declarations']),
    'methods' => $methods,
    'parameters' => $parameters,
    'errors' => $errors,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
exit($errors === [] ? 0 : 1);
