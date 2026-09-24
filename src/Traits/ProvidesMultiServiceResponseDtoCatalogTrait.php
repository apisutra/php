<?php

declare(strict_types=1);

namespace ApiSutra\Traits;

use ApiSutra\Contracts\Interfaces\Core\MultiServiceClientInterface;
use ApiSutra\Contracts\Interfaces\Inventory\ResponseDtoCatalogProviderInterface;
use ApiSutra\OperationInventory\Catalog\MultiServiceResponseDtoCatalogFactory;
use ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog;

/**
 * Sugar для мегаклиента: $mega->responseDtoCatalog().
 *
 * Trait тонкий — внутри только delegation в MultiServiceResponseDtoCatalogFactory
 * + кеширование на инстансе. Сам MultiServiceClientInterface остаётся
 * одно-методным; этот trait — DX-слой, не часть контракта.
 *
 * Класс, использующий trait, должен реализовывать:
 * - MultiServiceClientInterface (метод services())
 * - ResponseDtoCatalogProviderInterface (унифицированный контракт каталога)
 *
 * Это даёт single-shape DX поверх одиночных клиентов и мегаклиентов:
 *
 * ```php
 * /** @var array<int, ResponseDtoCatalogProviderInterface> $providers *\/
 * foreach ([$client, $mega] as $provider) {
 *     $provider->responseDtoCatalog();
 * }
 * ```
 *
 * @phpstan-require-implements MultiServiceClientInterface
 * @phpstan-require-implements ResponseDtoCatalogProviderInterface
 */
trait ProvidesMultiServiceResponseDtoCatalogTrait
{
    private ?ResponseDtoCatalog $multiServiceResponseDtoCatalog = null;

    public function responseDtoCatalog(): ResponseDtoCatalog
    {
        if ($this->multiServiceResponseDtoCatalog === null) {
            $this->multiServiceResponseDtoCatalog =
                (new MultiServiceResponseDtoCatalogFactory())->fromMultiService($this);
        }

        return $this->multiServiceResponseDtoCatalog;
    }
}
