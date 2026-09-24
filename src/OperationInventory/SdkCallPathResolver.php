<?php

declare(strict_types=1);

namespace ApiSutra\OperationInventory;

use ApiSutra\Core\AbstractRequest;
use ApiSutra\Core\AbstractResource;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

final readonly class SdkCallPathResolver
{
    public function __construct(
        private SdkCallPathMethodAnalyzer $methodAnalyzer,
    ) {
    }

    /**
     * @return array<class-string, array<int, string>>
     */
    public function resolveForClient(string $clientClass): array
    {
        if (!class_exists($clientClass)) {
            return [];
        }

        $paths = [];
        $this->walkClass(
            className: $clientClass,
            pathSegments: [],
            activeVersionKey: null,
            paths: $paths,
            stack: [],
        );

        foreach ($paths as &$requestPaths) {
            $requestPaths = array_values(array_unique($requestPaths));
            sort($requestPaths);
        }
        unset($requestPaths);

        ksort($paths);

        return $paths;
    }

    /**
     * @param array<class-string, array<int, string>> $paths
     * @param array<int, string> $pathSegments
     * @param array<int, string> $stack
     */
    private function walkClass(
        string $className,
        array $pathSegments,
        ?string $activeVersionKey,
        array &$paths,
        array $stack,
    ): void {
        if (!class_exists($className)) {
            return;
        }

        $stateKey = $className . '|' . ($activeVersionKey ?? '-');
        if (in_array($stateKey, $stack, true)) {
            return;
        }

        $stack[] = $stateKey;
        $reflection = new ReflectionClass($className);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($this->shouldSkipMethod($method)) {
                continue;
            }

            $segment = $method->getName() . '()';
            $nextSegments = [...$pathSegments, $segment];

            if ($this->isVersionShortcut($method, $activeVersionKey, $className)) {
                foreach ($this->versionShortcutTargets($method, $className) as $targetClass) {
                    $this->walkClass(
                        className: $targetClass,
                        pathSegments: $nextSegments,
                        activeVersionKey: $method->getName(),
                        paths: $paths,
                        stack: $stack,
                    );
                }

                continue;
            }

            if ($this->returnsSelfLike($method)) {
                continue;
            }

            $requestMap = $this->methodAnalyzer->extractRequestMap($method);
            if ($activeVersionKey !== null && isset($requestMap[$activeVersionKey])) {
                $this->appendPath(
                    paths: $paths,
                    requestClass: $requestMap[$activeVersionKey],
                    pathSegments: $nextSegments,
                );

                continue;
            }

            if ($requestMap !== []) {
                continue;
            }

            $resourceMap = $this->methodAnalyzer->extractResourceMap($method);
            if ($activeVersionKey !== null && isset($resourceMap[$activeVersionKey])) {
                $this->walkClass(
                    className: $resourceMap[$activeVersionKey],
                    pathSegments: $nextSegments,
                    activeVersionKey: $activeVersionKey,
                    paths: $paths,
                    stack: $stack,
                );

                continue;
            }

            if ($resourceMap !== []) {
                continue;
            }

            $requestTypes = $this->requestReturnTypes($method, $className);
            if ($requestTypes !== []) {
                foreach ($requestTypes as $requestClass) {
                    $this->appendPath(
                        paths: $paths,
                        requestClass: $requestClass,
                        pathSegments: $nextSegments,
                    );
                }

                continue;
            }

            $resourceTypes = $this->resourceReturnTypes($method, $className);
            if ($resourceTypes !== [] && $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            foreach ($resourceTypes as $resourceClass) {
                $this->walkClass(
                    className: $resourceClass,
                    pathSegments: $nextSegments,
                    activeVersionKey: $activeVersionKey,
                    paths: $paths,
                    stack: $stack,
                );
            }
        }
    }

    private function shouldSkipMethod(ReflectionMethod $method): bool
    {
        return $method->isConstructor()
            || $method->isDestructor()
            || $method->isStatic()
            || str_starts_with($method->getName(), '__');
    }

    private function isVersionShortcut(ReflectionMethod $method, ?string $activeVersionKey, string $contextClass): bool
    {
        if ($activeVersionKey !== null) {
            return false;
        }

        return $method->getNumberOfRequiredParameters() === 0
            && preg_match('/^v\d+$/', $method->getName()) === 1
            && $this->versionShortcutTargets($method, $contextClass) !== [];
    }

    private function returnsSelfLike(ReflectionMethod $method): bool
    {
        $type = $method->getReturnType();

        if (!$type instanceof ReflectionNamedType) {
            return false;
        }

        return in_array($type->getName(), ['self', 'static'], true);
    }

    /**
     * @return array<int, class-string<AbstractResource>>
     */
    private function versionShortcutTargets(ReflectionMethod $method, string $contextClass): array
    {
        if ($this->returnsSelfLike($method) && is_a($contextClass, AbstractResource::class, true)) {
            return [$contextClass];
        }

        return $this->resourceReturnTypes($method, $contextClass);
    }

    /**
     * @return array<int, class-string>
     */
    private function requestReturnTypes(ReflectionMethod $method, string $contextClass): array
    {
        return array_values(array_filter(
            $this->resolveReturnTypes($method->getReturnType(), $contextClass),
            static fn (string $type): bool => is_a($type, AbstractRequest::class, true),
        ));
    }

    /**
     * @return array<int, class-string<AbstractResource>>
     */
    private function resourceReturnTypes(ReflectionMethod $method, string $contextClass): array
    {
        return array_values(array_filter(
            $this->resolveReturnTypes($method->getReturnType(), $contextClass),
            static fn (string $type): bool => is_a($type, AbstractResource::class, true),
        ));
    }

    /**
     * @return array<int, class-string>
     */
    private function resolveReturnTypes(?ReflectionType $type, string $contextClass): array
    {
        if ($type instanceof ReflectionNamedType) {
            return $this->resolveNamedType($type, $contextClass);
        }

        if ($type instanceof ReflectionUnionType) {
            $resolved = [];
            foreach ($type->getTypes() as $unionType) {
                $resolved = [...$resolved, ...$this->resolveNamedType($unionType, $contextClass)];
            }

            return array_values(array_unique($resolved));
        }

        return [];
    }

    /**
     * @return array<int, class-string>
     */
    private function resolveNamedType(ReflectionNamedType $type, string $contextClass): array
    {
        if ($type->isBuiltin()) {
            return [];
        }

        $name = $type->getName();
        $resolved = match ($name) {
            'self', 'static' => $contextClass,
            'parent' => get_parent_class($contextClass) ?: null,
            default => $name,
        };

        if (!is_string($resolved) || !class_exists($resolved)) {
            return [];
        }

        return [$resolved];
    }

    /**
     * @param array<class-string, array<int, string>> $paths
     * @param array<int, string> $pathSegments
     */
    private function appendPath(array &$paths, string $requestClass, array $pathSegments): void
    {
        $paths[$requestClass] ??= [];
        $paths[$requestClass][] = implode('->', $pathSegments);
    }
}
