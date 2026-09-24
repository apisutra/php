<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

final readonly class OwnerDto
{
    public function __construct(public int $id, public array $extra = [])
    {
    }
}
