<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\PolymorphicInput;

use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Rules\ScalarType;
use ApiSutra\Serialization\Shapes\DtoShape;
use ApiSutra\Serialization\Shapes\ListShape;
use ApiSutra\Serialization\Shapes\NullableShape;

final readonly class MessageEvent implements Event
{
    /** @param list<string> $permissions */
    public function __construct(
        public string $text = 'default',
        #[Shape(new NullableShape(new DtoShape(Event::class)))] public ?Event $next = null,
        #[Shape(new ListShape(ScalarType::String))] public array $permissions = [],
    ) {
    }
}
