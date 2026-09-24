<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Inventory\Resources;

use ApiSutra\Core\AbstractResource;
use ApiSutra\Tests\Stubs\Inventory\Requests\InventoryListOrdersRequest;
use ApiSutra\Tests\Stubs\Inventory\Requests\InventoryOrderDetailsRequest;

final class InventoryOrdersResource extends AbstractResource
{
    public function listAll(): InventoryListOrdersRequest
    {
        return $this->request(InventoryListOrdersRequest::class);
    }

    public function get(string $orderId): InventoryOrderDetailsRequest
    {
        return $this->request(InventoryOrderDetailsRequest::class, $orderId);
    }

    public function download(string $orderId): InventoryOrderDetailsRequest
    {
        return $this->get($orderId);
    }

    public function sendNow(string $orderId)
    {
        return $this->get($orderId)->send();
    }
}
