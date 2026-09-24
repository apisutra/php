<?php

declare(strict_types=1);

namespace Example\HydrationRules;

final readonly class EntryDto
{
    public function __construct(public int $id, public array $_extra = [])
    {
    }
}
