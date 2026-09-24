<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ConstructorOwned;

final class MutableDto
{
    public string $value;
    public int $id;

    public function __construct()
    {
        State::$calls++;
        $this->value = 'known';
    }
}
