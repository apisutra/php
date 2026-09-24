<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Catalog\Resources\Reports\Tasks\Requests\CreateByFio\Dto;

final readonly class CatalogCreateByFioFinalDto
{
    public function __construct(
        public string $reportId,
    ) {}
}
