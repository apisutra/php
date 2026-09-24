<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\DtoMapping;

use ApiSutra\Contracts\Interfaces\Serialization\DtoHydratorInterface;
use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Tests\Stubs\HydrationRules\RecordDto;
use Override;

final class LocalHydrator implements DtoHydratorInterface
{
    #[Override]
    public function supports(string $dtoClass): bool
    {
        return $dtoClass === RecordDto::class;
    }

    #[Override]
    public function hydrate(array|object $data, string $dtoClass, HydrationContext $context): object
    {
        return new RecordDto(20);
    }
}
