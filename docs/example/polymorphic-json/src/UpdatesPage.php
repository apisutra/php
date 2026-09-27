<?php

declare(strict_types=1);

namespace Example\PolymorphicJson;

use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Shapes\DtoShape;
use ApiSutra\Serialization\Shapes\ListShape;

final readonly class UpdatesPage
{
    /** @param list<Update> $updates */
    public function __construct(#[Shape(new ListShape(new DtoShape(Update::class)))] public array $updates)
    {
    }
}
