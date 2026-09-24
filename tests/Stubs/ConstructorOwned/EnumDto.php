<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ConstructorOwned;

final class EnumDto
{
    public Kind $value;

    public function __construct()
    {
        State::$calls++;
        $this->value = Kind::Known;
    }
}
