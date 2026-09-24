<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ConstructorOwned;

final readonly class BoolIntDto
{
    public bool|int $value;

    public function __construct()
    {
        State::$calls++;
        $this->value = State::$value;
    }
}
