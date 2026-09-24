<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

final readonly class PlainRecord
{
    public string $kind;

    public function __construct(
        public int $id,
        public array $rows = [],
        public ?int $stock = null,
        public array $extra = [],
    ) {
        $this->kind = 'record';
    }
}
