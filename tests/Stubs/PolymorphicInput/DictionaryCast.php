<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\PolymorphicInput;

use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Serialization\Context\HydrationContext;
use Override;

final class DictionaryCast implements HydrationCastInterface
{
    public static int $calls = 0;

    #[Override]
    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        self::$calls++;
        return $value;
    }
}
