<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\RequiredInput;

final class StaticField
{
    #[RequiredInput] public static int $id = 0;
}
