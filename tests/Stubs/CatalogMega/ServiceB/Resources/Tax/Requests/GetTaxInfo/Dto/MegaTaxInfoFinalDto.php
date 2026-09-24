<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\GetTaxInfo\Dto;

final readonly class MegaTaxInfoFinalDto
{
    public function __construct(
        public string $reportId,
    ) {}
}
