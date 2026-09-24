<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\DtoMapping;

use ApiSutra\Contracts\Interfaces\Serialization\DtoHydratorInterface;
use ApiSutra\Serialization\Context\HydrationContext;
use Closure;
use Override;

final class CallbackHydrator implements DtoHydratorInterface
{
    /** @param list<class-string>|null $classes */
    public function __construct(private Closure $callback, private ?array $classes = null) {}

    #[Override]
    public function supports(string $dtoClass): bool
    {
        return $this->classes === null || in_array($dtoClass, $this->classes, true);
    }

    #[Override]
    public function hydrate(array|object $data, string $dtoClass, HydrationContext $context): object
    {
        return ($this->callback)($data, $dtoClass, $context);
    }
}
