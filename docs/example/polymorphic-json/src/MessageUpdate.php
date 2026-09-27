<?php

declare(strict_types=1);

namespace Example\PolymorphicJson;

use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Shapes\ListShape;
use ApiSutra\Serialization\Rules\ScalarType;

final readonly class MessageUpdate implements Update
{
    /** @param list<string> $permissions */
    public function __construct(
        #[Nested] public Message $message,
        #[Shape(new ListShape(ScalarType::String))] public array $permissions,
    ) {
    }
}
