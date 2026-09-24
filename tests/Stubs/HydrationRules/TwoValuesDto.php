<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

final readonly class TwoValuesDto
{
    public function __construct(public mixed $first, public mixed $second, public array $extra = [])
    {
    }
}
