<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ConstructorOwned;

final readonly class TrueDto
{
    public true $value;

    public function __construct()
    {
        State::$calls++;
        $this->value = State::$value;
    }
}
