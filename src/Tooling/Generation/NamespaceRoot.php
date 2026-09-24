<?php

declare(strict_types=1);

namespace ApiSutra\Tooling\Generation;

use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;
use JsonException;

/** Выбор PSR-4 корня без изменения Composer и угадывания между несколькими каталогами. */
final readonly class NamespaceRoot
{
    public function __construct(public string $namespace, public string $directory)
    {
    }

    public static function resolve(string $project, ?string $namespace = null, ?string $directory = null): self
    {
        $namespace = $namespace === null ? null : trim($namespace, '\\');
        if ($namespace !== null) {
            self::validateName($namespace);
        }
        if ($directory !== null) {
            if ($namespace === null || $directory === '') {
                throw new ConfigurationException(new Message('generation.explicit_root'));
            }
            return new self($namespace, self::absolute($project, $directory));
        }
        $manifest = $project . '/composer.json';
        try {
            $json = is_file($manifest) ? json_decode((string) file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR) : null;
        } catch (JsonException $exception) {
            throw new ConfigurationException(new Message('generation.invalid_composer'), previous: $exception);
        }
        $map = is_array($json) ? ($json['autoload']['psr-4'] ?? []) : [];
        $roots = [];
        if (is_array($map)) {
            foreach ($map as $prefix => $paths) {
                if (!is_string($prefix) || trim($prefix, '\\') === '') {
                    continue;
                }
                $prefix = trim($prefix, '\\');
                self::validateName($prefix);
                if ($namespace !== null && $namespace !== $prefix && !str_starts_with($namespace, $prefix . '\\')) {
                    continue;
                }
                foreach (is_array($paths) ? $paths : [$paths] as $path) {
                    if (!is_string($path) || $path === '') {
                        continue;
                    }
                    $suffix = $namespace === null ? '' : substr($namespace, strlen($prefix));
                    $roots[] = [strlen($prefix), new self($namespace ?? $prefix, self::absolute($project, $path) . str_replace('\\', '/', $suffix))];
                }
            }
        }
        if ($namespace !== null && $roots !== []) {
            $length = max(array_column($roots, 0));
            $roots = array_values(array_filter($roots, static fn (array $root): bool => $root[0] === $length));
        }
        if (count($roots) !== 1) {
            throw new ConfigurationException(new Message('generation.ambiguous_root'));
        }
        return $roots[0][1];
    }

    public static function validateName(string $name): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D', $name) !== 1) {
            throw new ConfigurationException(new Message('generation.invalid_name', ['name' => $name]));
        }
        $reserved = ['class', 'trait', 'interface', 'enum', 'namespace', 'function', 'const', 'new', 'extends', 'implements',
            'self', 'parent', 'static', 'true', 'false', 'null', 'int', 'float', 'string', 'bool', 'void', 'mixed', 'never',
            'object', 'iterable', 'array', 'callable', 'resource', 'match', 'readonly', 'abstract', 'final', 'public',
            'protected', 'private', 'return', 'throw', 'try', 'catch', 'finally', 'use', 'as', 'for', 'foreach', 'while',
            'do', 'if', 'else', 'elseif', 'switch', 'case', 'default', 'break', 'continue', 'yield', 'fn', 'echo', 'print',
            'exit', 'die', 'global', 'unset', 'isset', 'empty', 'list', 'include', 'include_once', 'require', 'require_once',
            'and', 'or', 'xor', 'instanceof', 'insteadof', 'goto', 'declare', 'enddeclare', 'endif', 'endfor', 'endforeach', 'endswitch', 'endwhile'];
        foreach (explode('\\', $name) as $part) {
            if (in_array(strtolower($part), $reserved, true)) {
                throw new ConfigurationException(new Message('generation.invalid_name', ['name' => $name]));
            }
        }
    }

    private static function absolute(string $project, string $path): string
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
            ? rtrim($path, '/') : rtrim($project, '/') . '/' . rtrim($path, '/');
    }
}
