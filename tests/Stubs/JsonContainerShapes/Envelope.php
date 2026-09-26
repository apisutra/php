<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\JsonContainerShapes;

use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Rules\ScalarType;
use ApiSutra\Serialization\Shapes\DtoShape;
use ApiSutra\Serialization\Shapes\ListShape;
use ApiSutra\Tests\Stubs\AttributeHydration\Row;

final readonly class Envelope
{
    public function __construct(
        #[Shape(new ListShape(ScalarType::String))] public array $items = [],
        #[Shape(new DtoShape(Node::class))] public ?Node $payload = null,
        #[Shape(new DtoShape(Node::class, emptyListAsObject: true))] public ?Node $options = null,
        #[Shape(new DtoShape(Row::class, emptyListAsObject: true))] public ?Row $required = null,
        #[Nested] public ?Node $nested = null,
        public ?Row $native = null,
        #[Shape(new ListShape(ScalarType::String, normalizeKeys: true))] public array $normalized = [],
        #[From('missing', fallback: ['response.rows'])]
        #[Shape(new ListShape(new DtoShape(Node::class), each: 'value'))] public array $rows = [],
    ) {
    }
}
