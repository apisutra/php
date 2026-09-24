<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\Attributes\DataTransfer\RequiredInput;

final readonly class PlainRow
{
    public function __construct(#[RequiredInput] public int $id = 0, #[Extras] public array $_extra = [])
    {
    }
}
