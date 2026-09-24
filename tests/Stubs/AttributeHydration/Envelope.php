<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Serialization\Shapes\ListShape;
use ApiSutra\Serialization\Shapes\NullableShape;
use ApiSutra\Serialization\Shapes\DtoShape;

final readonly class Envelope
{
    public function __construct(
        #[From('rows', fallback: ['legacy'])]
        #[Shape(new ListShape(new DtoShape(Row::class), each: 'value'))]
        public array $items = [],
        #[Shape(new NullableShape(new DtoShape(Row::class)))]
        public ?Row $child = null,
        #[Extras] public array $_extra = [],
    ) {
    }
}
