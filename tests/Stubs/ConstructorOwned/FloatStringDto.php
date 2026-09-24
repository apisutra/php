<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ConstructorOwned;

final readonly class FloatStringDto
{
    public float|string $value;

    public function __construct()
    {
        State::$calls++;
        $this->value = State::$value;
    }
}
