<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

final readonly class PlainChildDto
{
    public function __construct(public RecordDto $child)
    {
    }
}
