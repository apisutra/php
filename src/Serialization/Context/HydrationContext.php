<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Context;

use ApiSutra\Serialization\Rules\HydrationScope;
use ApiSutra\Serialization\Integration\HttpMappingContext;
use Closure;

final class HydrationContext
{
    use InvocationLifetime;

    /** @param array<class-string, object> $extensions */
    private function __construct(private ?HydrationScope $scope, array $extensions)
    {
        $this->extensions = $extensions;
    }

    /**
     * @internal
     * @param array<class-string, object> $extensions
     * @param Closure(self): mixed $operation
     */
    public static function invoke(HydrationScope $scope, array $extensions, Closure $operation): mixed
    {
        $context = new self($scope, $extensions);
        try {
            return $operation($context);
        } finally {
            $context->scope = null;
            $context->closeLifetime();
        }
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function hydrate(array|object $data, string $class): object
    {
        $this->assertActive();
        /** @var T */
        return $this->scope->hydrate($data, $class);
    }

    /**
     * @template T of object
     * @param array<array-key, mixed> $items
     * @param class-string<T> $class
     * @return list<T>
     */
    public function hydrateCollection(array $items, string $class): array
    {
        $this->assertActive();
        /** @var list<T> */
        return $this->scope->hydrateCollection($items, $class);
    }

    public function http(): ?HttpMappingContext
    {
        return $this->extension(HttpMappingContext::class);
    }
}
