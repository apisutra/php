<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\PolymorphicInput;

use ApiSutra\Collections\AbstractTypedCollection;
use Override;

/** @extends AbstractTypedCollection<ExternalEvent> */
final readonly class ExternalCollection extends AbstractTypedCollection
{
    #[Override]
    protected static function itemClass(): string
    {
        return ExternalEvent::class;
    }
}
