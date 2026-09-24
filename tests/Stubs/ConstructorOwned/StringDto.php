<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ConstructorOwned;

final readonly class StringDto
{
    public string $value;

    public function __construct()
    {
        State::$calls++;
        $this->value = State::$value;
    }
}
