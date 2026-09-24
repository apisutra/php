<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Inventory;

use ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog;

/**
 * Унифицированный контракт «отдай мне каталог response DTO».
 *
 * Имплементируется:
 * - AbstractClient — single-client каталог (serviceClass у usages = null)
 * - мегаклиентом через ProvidesMultiServiceResponseDtoCatalogTrait —
 *   multi-service каталог (serviceClass проставлен на каждый usage)
 *
 * Контракт даёт статический полиморфизм в DX:
 *
 * ```php
 * /** @var array<int, ResponseDtoCatalogProviderInterface> $providers *\/
 * foreach ([$client, $kontur, $tax] as $provider) {
 *     foreach ($provider->responseDtoCatalog()->usages() as $dto => $usages) {
 *         // ...
 *     }
 * }
 * ```
 */
interface ResponseDtoCatalogProviderInterface
{
    public function responseDtoCatalog(): ResponseDtoCatalog;
}
