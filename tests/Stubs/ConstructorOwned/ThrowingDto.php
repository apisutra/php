<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ConstructorOwned;

use LogicException;

final class ThrowingDto
{
    public string $value;

    public function __construct()
    {
        State::$calls++;
        throw new LogicException("constructor-error");
    }
}
