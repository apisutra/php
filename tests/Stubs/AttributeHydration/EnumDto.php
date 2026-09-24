<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\ConstructorValue;
use ApiSutra\Tests\Stubs\ConstructorOwned\State;
use ApiSutra\Tests\Stubs\ConstructorOwned\Kind;

final class EnumDto
{
    #[ConstructorValue]
    public Kind $value;

    public function __construct()
    {
        State::$calls++;
        $this->value = Kind::Known;
    }
}
