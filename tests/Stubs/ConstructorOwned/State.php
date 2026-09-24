<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ConstructorOwned;

final class State
{
    public static mixed $value = 'known';
    public static int $calls = 0;
    public static int $handlers = 0;
}
