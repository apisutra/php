<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\Attributes\DataTransfer\RequiredInput;

readonly class ParentRow
{
    public function __construct(#[RequiredInput] public int $id = 0, #[Extras] public array $_extra = [])
    {
    }
}
