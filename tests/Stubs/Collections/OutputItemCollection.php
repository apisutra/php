<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Collections;

use ApiSutra\Collections\AbstractTypedCollection;
use ApiSutra\Tests\Stubs\Dto\OutputItemDto;

/**
 * @extends AbstractTypedCollection<OutputItemDto>
 */
final readonly class OutputItemCollection extends AbstractTypedCollection
{
    protected static function itemClass(): string
    {
        return OutputItemDto::class;
    }
}
