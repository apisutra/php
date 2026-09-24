<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

final readonly class NodeDto
{
    public function __construct(public ?NodeDto $child = null)
    {
    }
}
