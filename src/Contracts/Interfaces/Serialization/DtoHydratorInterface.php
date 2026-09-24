<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Serialization;

use ApiSutra\Serialization\Context\HydrationContext;

interface DtoHydratorInterface
{
    /** @param class-string $dtoClass */
    public function supports(string $dtoClass): bool;

    /** @param class-string $dtoClass */
    public function hydrate(array|object $data, string $dtoClass, HydrationContext $context): object;
}
