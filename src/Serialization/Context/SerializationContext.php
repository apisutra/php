<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Context;

use Closure;

final class SerializationContext
{
    use InvocationLifetime;

    /**
     * @param Closure(object): array<array-key, mixed> $serializer
     * @param array<class-string, object> $extensions
     */
    private function __construct(private ?Closure $serializer, array $extensions)
    {
        $this->extensions = $extensions;
    }

    /**
     * @internal
     * @param Closure(object): array<array-key, mixed> $serializer
     * @param array<class-string, object> $extensions
     * @param Closure(self): mixed $operation
     */
    public static function invoke(Closure $serializer, array $extensions, Closure $operation): mixed
    {
        $context = new self($serializer, $extensions);
        try {
            return $operation($context);
        } finally {
            $context->serializer = null;
            $context->closeLifetime();
        }
    }

    /** @return array<array-key, mixed> */
    public function serialize(object $dto): array
    {
        $this->assertActive();
        return ($this->serializer)($dto);
    }
}
