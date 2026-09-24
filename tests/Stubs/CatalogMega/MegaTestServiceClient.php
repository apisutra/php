<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\CatalogMega;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Contracts\Interfaces\Inventory\OperationInventoryInterface;
use ApiSutra\Core\AbstractClient;

/**
 * Базовый stub-клиент для multi-service catalog тестов:
 * - принимает явный inventory, чтобы каждый сервис ограничивался своим
 *   namespace без зависимости от inferRootNamespace().
 */
abstract class MegaTestServiceClient extends AbstractClient
{
    public function __construct(
        ClientConfig $config,
        TransportInterface $transport,
        private readonly OperationInventoryInterface $injectedInventory,
    ) {
        parent::__construct($config, $transport);
    }

    #[\Override]
    public function operationInventory(): OperationInventoryInterface
    {
        return $this->injectedInventory;
    }
}
