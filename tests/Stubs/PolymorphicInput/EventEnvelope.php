<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\PolymorphicInput;

use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Shapes\DtoShape;
use ApiSutra\Serialization\Shapes\ListShape;

final readonly class EventEnvelope
{
    /** @param list<Event> $items */
    public function __construct(
        public ?Event $native,
        #[Nested] public Event $nested,
        #[Shape(new DtoShape(Event::class))] public Event $shaped,
        #[Shape(new ListShape(new DtoShape(Event::class)))] public array $items,
    ) {
    }
}
