<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Inventory;

use ApiSutra\OperationInventory\OperationDescriptorView;

interface OperationInventoryInterface
{
    /**
     * @return array<int, OperationDescriptorView>
     */
    public function all(): array;

    public function forRequest(string $requestClass): ?OperationDescriptorView;

    /**
     * @return array<int, OperationDescriptorView>
     */
    public function forEndpoint(string $endpoint): array;
}
