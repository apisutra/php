<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ConstructorOwned;

final class GraphDto
{
    public function __construct(public array $items)
    {
    }
}
