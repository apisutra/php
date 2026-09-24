<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ConstructorOwned;

final class UnitDto
{
    public Flag $value;

    public function __construct()
    {
        State::$calls++;
        $this->value = Flag::Known;
    }
}
