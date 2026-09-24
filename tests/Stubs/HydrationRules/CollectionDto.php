<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

final readonly class CollectionDto
{
    public function __construct(public RecordCollection $items = new RecordCollection())
    {
    }
}
