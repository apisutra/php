<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Rules\HandlerSpec;
use ApiSutra\Serialization\Shapes\DtoShape;
use ApiSutra\Serialization\Shapes\ListShape;
use ApiSutra\Tests\Stubs\HydrationRules\RecordDto;
use ApiSutra\Tests\Stubs\HydrationRules\ScopedChildCast;

final readonly class ScopedRows
{
    public function __construct(
        #[Shape(new ListShape(new ListShape(new DtoShape(RecordDto::class), itemCast: new HandlerSpec(ScopedChildCast::class))))]
        public array $rows,
    ) {
    }
}
