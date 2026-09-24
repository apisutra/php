<?php

declare(strict_types=1);

namespace ApiSutra\OperationInventory;

use BackedEnum;
use ReflectionClass;
use ReflectionMethod;
use UnitEnum;
use Throwable;

final class SdkCallPathMethodAnalyzer
{
    /**
     * @var array<string, array{namespace:string, imports:array<string, string>}>
     */
    private array $fileContextCache = [];

    /**
     * @return array<string, class-string>
     */
    public function extractRequestMap(ReflectionMethod $method): array
    {
        return $this->extractClassMap($method, 'requestByVersion');
    }

    /**
     * @return array<string, class-string>
     */
    public function extractResourceMap(ReflectionMethod $method): array
    {
        return $this->extractClassMap($method, 'resourceByVersion');
    }

    /**
     * @return array<string, class-string>
     */
    private function extractClassMap(ReflectionMethod $method, string $helperName): array
    {
        $file = $method->getFileName();
        if ($file === false || !is_file($file)) {
            return [];
        }

        $source = $this->methodSource($method, $file);
        if ($source === null) {
            return [];
        }

        $arrayBody = $this->firstArrayArgument($source, $helperName);
        if ($arrayBody === null) {
            return [];
        }

        $fileContext = $this->fileContext($file, $method->getDeclaringClass());
        $entries = $this->splitEntries($arrayBody);
        $resolved = [];

        foreach ($entries as $entry) {
            $parts = explode('=>', $entry, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = $this->resolveKeyExpression(
                trim($parts[0]),
                $method->getDeclaringClass(),
                $fileContext['namespace'],
                $fileContext['imports'],
            );
            $class = $this->resolveClassExpression(
                trim($parts[1]),
                $fileContext['namespace'],
                $fileContext['imports'],
            );

            if ($key === null || $class === null) {
                continue;
            }

            $resolved[$key] = $class;
        }

        return $resolved;
    }

    private function methodSource(ReflectionMethod $method, string $file): ?string
    {
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        if ($startLine === false || $endLine === false || $endLine < $startLine) {
            return null;
        }

        $lines = file($file);
        if (!is_array($lines)) {
            return null;
        }

        return implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
    }

    private function firstArrayArgument(string $source, string $helperName): ?string
    {
        $pattern = sprintf('/%s\s*\(\s*\[(?<map>.*?)\]\s*(?:,|\))/s', preg_quote($helperName, '/'));
        $matched = preg_match($pattern, $source, $matches);
        if ($matched !== 1) {
            return null;
        }

        $map = $matches['map'] ?? null;

        return is_string($map) ? $map : null;
    }

    /**
     * @return array<int, string>
     */
    private function splitEntries(string $arrayBody): array
    {
        $entries = preg_split('/,(?![^\\(]*\\))/', $arrayBody) ?: [];

        return array_values(array_filter(array_map('trim', $entries), static fn (string $entry): bool => $entry !== ''));
    }

    /**
     * @return array{namespace:string, imports:array<string, string>}
     */
    private function fileContext(string $file, ReflectionClass $class): array
    {
        if (isset($this->fileContextCache[$file])) {
            return $this->fileContextCache[$file];
        }

        $content = file_get_contents($file);
        if (!is_string($content)) {
            $context = [
                'namespace' => $class->getNamespaceName(),
                'imports' => [],
            ];
            $this->fileContextCache[$file] = $context;

            return $context;
        }

        $tokens = token_get_all($content);
        $namespace = $class->getNamespaceName();
        $imports = [];
        $depth = 0;
        $tokenCount = count($tokens);

        for ($index = 0; $index < $tokenCount; $index++) {
            $token = $tokens[$index];

            if (is_string($token)) {
                if ($token === '{') {
                    $depth++;
                } elseif ($token === '}') {
                    $depth = max(0, $depth - 1);
                }

                continue;
            }

            [$tokenId, $tokenText] = $token;

            if ($depth !== 0) {
                continue;
            }

            if ($tokenId === T_NAMESPACE) {
                $namespace = $this->consumeNamespace($tokens, $index + 1);
                continue;
            }

            if ($tokenId === T_USE) {
                $statement = $this->consumeUseStatement($tokens, $index + 1);
                foreach ($this->parseUseStatement($statement) as $alias => $fqcn) {
                    $imports[$alias] = $fqcn;
                }
            }
        }

        $context = [
            'namespace' => $namespace,
            'imports' => $imports,
        ];
        $this->fileContextCache[$file] = $context;

        return $context;
    }

    /**
     * @param array<int, mixed> $tokens
     */
    private function consumeNamespace(array $tokens, int $index): string
    {
        $parts = [];
        $tokenCount = count($tokens);

        for (; $index < $tokenCount; $index++) {
            $token = $tokens[$index];
            if (is_string($token)) {
                if ($token === ';' || $token === '{') {
                    break;
                }

                continue;
            }

            if (in_array($token[0], [T_STRING, T_NS_SEPARATOR], true)) {
                $parts[] = $token[1];
            }
        }

        return implode('', $parts);
    }

    /**
     * @param array<int, mixed> $tokens
     */
    private function consumeUseStatement(array $tokens, int $index): string
    {
        $parts = [];
        $tokenCount = count($tokens);

        for (; $index < $tokenCount; $index++) {
            $token = $tokens[$index];
            if (is_string($token)) {
                if ($token === ';') {
                    break;
                }

                $parts[] = $token;
                continue;
            }

            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $parts[] = $token[1];
        }

        return implode('', $parts);
    }

    /**
     * @return array<string, string>
     */
    private function parseUseStatement(string $statement): array
    {
        $statement = trim($statement);
        if ($statement === '' || str_starts_with($statement, 'function ') || str_starts_with($statement, 'const ')) {
            return [];
        }

        if (str_contains($statement, '{')) {
            return [];
        }

        $imports = [];
        foreach (array_map('trim', explode(',', $statement)) as $use) {
            if ($use === '') {
                continue;
            }

            $parts = preg_split('/\s+as\s+/i', $use) ?: [];
            $fqcn = trim($parts[0] ?? '', '\\');
            if ($fqcn === '') {
                continue;
            }

            $alias = $parts[1] ?? basename(str_replace('\\', '/', $fqcn));
            $imports[$alias] = $fqcn;
        }

        return $imports;
    }

    /**
     * @param array<string, string> $imports
     */
    private function resolveKeyExpression(
        string $expression,
        ReflectionClass $class,
        string $namespace,
        array $imports,
    ): ?string {
        $expression = trim($expression, " \t\n\r\0\x0B,");
        if ($expression === '') {
            return null;
        }

        if (preg_match('/^[\'"](?<value>.*)[\'"]$/s', $expression, $matches) === 1) {
            $value = $matches['value'] ?? null;

            return is_string($value) ? $value : null;
        }

        if (is_numeric($expression)) {
            return (string) $expression;
        }

        if (
            preg_match('/^(?<class>[\w\\\\]+)::(?<member>\w+)(?:->(?<property>value|name))?$/', $expression, $matches) !== 1
        ) {
            return null;
        }

        $resolvedClass = $this->resolveClassName(
            $matches['class'],
            $namespace,
            $imports,
            $class->getName(),
        );
        $member = $matches['member'];
        $property = $matches['property'] ?? null;

        try {
            $value = constant($resolvedClass . '::' . $member);
        } catch (Throwable) {
            return null;
        }

        if ($property === 'value') {
            if ($value instanceof BackedEnum) {
                return (string) $value->value;
            }

            return null;
        }

        if ($property === 'name') {
            return $value instanceof UnitEnum ? $value->name : null;
        }

        if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        return null;
    }

    /**
     * @param array<string, string> $imports
     * @return class-string|null
     */
    private function resolveClassExpression(string $expression, string $namespace, array $imports): ?string
    {
        $expression = trim($expression, " \t\n\r\0\x0B,");
        if (!str_ends_with($expression, '::class')) {
            return null;
        }

        $name = substr($expression, 0, -7);
        if (!is_string($name) || $name === '') {
            return null;
        }

        $resolved = $this->resolveClassName($name, $namespace, $imports);

        return class_exists($resolved) ? $resolved : null;
    }

    /**
     * @param array<string, string> $imports
     * @return class-string
     */
    private function resolveClassName(
        string $name,
        string $namespace,
        array $imports,
        ?string $selfClass = null,
    ): string {
        $name = trim($name);

        return match ($name) {
            'self', 'static' => $selfClass ?? $name,
            default => $this->resolveImportedClassName($name, $namespace, $imports),
        };
    }

    /**
     * @param array<string, string> $imports
     * @return class-string
     */
    private function resolveImportedClassName(string $name, string $namespace, array $imports): string
    {
        if (str_starts_with($name, '\\')) {
            /** @var class-string */
            return ltrim($name, '\\');
        }

        $segments = explode('\\', $name);
        $alias = $segments[0];

        if (isset($imports[$alias])) {
            $prefix = $imports[$alias];
            $suffix = array_slice($segments, 1);
            $resolved = $suffix === [] ? $prefix : $prefix . '\\' . implode('\\', $suffix);

            /** @var class-string */
            return $resolved;
        }

        /** @var class-string */
        return $namespace !== '' ? $namespace . '\\' . $name : $name;
    }
}
