<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\Extras;

final readonly class TwoInheritedReceivers extends ParentRow
{
    #[Extras] public array $another;
}
