<?php

declare(strict_types=1);

namespace Example\DtoShowcase;

use ApiSutra\Collections\AbstractTypedCollection;

/** @extends AbstractTypedCollection<TagDto> */
final readonly class TagCollection extends AbstractTypedCollection
{
    protected static function itemClass(): string
    {
        return TagDto::class;
    }
}
