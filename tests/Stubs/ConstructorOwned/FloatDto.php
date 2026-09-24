<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ConstructorOwned;

final readonly class FloatDto
{
    public float $value;

    public function __construct()
    {
        State::$calls++;
        $this->value = State::$value;
    }
}
