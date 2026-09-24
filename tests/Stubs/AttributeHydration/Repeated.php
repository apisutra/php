<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\RequiredInput;

final class Repeated
{
    #[RequiredInput] #[RequiredInput] public int $id = 0;
}
