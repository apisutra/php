<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\GetTaxInfo\Dto;

final readonly class MegaTaxInfoStartDto
{
    public function __construct(
        public string $taskId,
    ) {}
}
