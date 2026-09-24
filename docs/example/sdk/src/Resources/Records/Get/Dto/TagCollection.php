<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records\Get\Dto;

use ApiSutra\Collections\AbstractTypedCollection;
use Override;

/** @extends AbstractTypedCollection<TagDto> */
final readonly class TagCollection extends AbstractTypedCollection
{
    #[Override]
    protected static function itemClass(): string
    {
        return TagDto::class;
    }
}
