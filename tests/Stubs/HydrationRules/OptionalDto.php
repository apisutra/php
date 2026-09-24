<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

final readonly class OptionalDto
{
    public function __construct(public ?int $count = null, public array $extra = [])
    {
    }
}
