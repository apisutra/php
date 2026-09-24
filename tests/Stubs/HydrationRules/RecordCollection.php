<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Collections\AbstractTypedCollection;

/** @extends AbstractTypedCollection<RecordDto> */
final readonly class RecordCollection extends AbstractTypedCollection
{
    protected static function itemClass(): string
    {
        return RecordDto::class;
    }
}
