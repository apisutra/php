<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\ConstructorValue;
use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\Attributes\DataTransfer\RequiredInput;
use ApiSutra\Attributes\DataTransfer\ForbidExplicitNull;
use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Serialization\Rules\ScalarType;
use ApiSutra\Serialization\Shapes\ListShape;

final readonly class Record
{
    #[ConstructorValue]
    public string $kind;

    public function __construct(
        #[From('record_id')]
        #[RequiredInput]
        public int $id,
        #[Shape(new ListShape(new ListShape(ScalarType::Int)))]
        public array $rows = [],
        #[ForbidExplicitNull]
        public ?int $stock = null,
        #[Extras]
        public array $extra = [],
    ) {
        $this->kind = 'record';
    }
}
