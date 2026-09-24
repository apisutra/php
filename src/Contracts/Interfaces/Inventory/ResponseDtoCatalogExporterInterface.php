<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Inventory;

use ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog;

/**
 * Контракт экспортёра каталога response DTO.
 *
 * Каждый экспортёр отвечает за один format (например, 'md', 'json', 'openapi').
 * Сам каталог про форматирование ничего не знает.
 */
interface ResponseDtoCatalogExporterInterface
{
    /**
     * Идентификатор формата (lowercase, без точки), например: 'md', 'json'.
     */
    public function format(): string;

    public function export(ResponseDtoCatalog $catalog): string;
}
