<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

final readonly class TaggedDto
{
    public function __construct(public int $id, public string $type, public array $extra = [])
    {
    }
}
