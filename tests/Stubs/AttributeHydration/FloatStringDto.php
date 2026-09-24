<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\ConstructorValue;
use ApiSutra\Tests\Stubs\ConstructorOwned\State;
use ApiSutra\Tests\Stubs\ConstructorOwned\Kind;

final readonly class FloatStringDto
{
    #[ConstructorValue]
    public float|string $value;

    public function __construct()
    {
        State::$calls++;
        $this->value = State::$value;
    }
}
