<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

final readonly class ValueDto
{
    public function __construct(public mixed $value, public array $extra = [])
    {
    }
}
