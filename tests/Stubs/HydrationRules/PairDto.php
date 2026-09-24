<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

final readonly class PairDto
{
    public function __construct(public NodeDto $left, public NodeDto $right)
    {
    }
}
