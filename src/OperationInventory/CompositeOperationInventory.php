<?php

declare(strict_types=1);

namespace ApiSutra\OperationInventory;

use ApiSutra\Contracts\Interfaces\Inventory\OperationInventoryInterface;

/**
 * Композитный inventory поверх нескольких готовых OperationInventoryInterface.
 *
 * Используется для multi-service сценария (мегаклиент): каждый сервис продолжает
 * строить свой inventory обычным способом, а composite только агрегирует их.
 *
 * Поведение:
 * - all() — конкатенация all() всех inventories в порядке передачи (без глобальной пересортировки)
 * - forRequest() — первое совпадение по порядку (детерминированно)
 * - forEndpoint() — конкатенация совпадений из всех inventories
 *
 * Доп. helper inventories() даёт доступ к исходным per-service inventory без
 * расширения публичного интерфейса.
 */
final readonly class CompositeOperationInventory implements OperationInventoryInterface
{
    /** @var array<int, OperationInventoryInterface> */
    private array $inventories;

    /**
     * @param array<int, OperationInventoryInterface> $inventories
     */
    public function __construct(array $inventories)
    {
        $this->inventories = array_values($inventories);
    }

    /**
     * @return array<int, OperationDescriptorView>
     */
    #[\Override]
    public function all(): array
    {
        $items = [];
        foreach ($this->inventories as $inventory) {
            foreach ($inventory->all() as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    #[\Override]
    public function forRequest(string $requestClass): ?OperationDescriptorView
    {
        foreach ($this->inventories as $inventory) {
            $found = $inventory->forRequest($requestClass);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @return array<int, OperationDescriptorView>
     */
    #[\Override]
    public function forEndpoint(string $endpoint): array
    {
        $matches = [];
        foreach ($this->inventories as $inventory) {
            foreach ($inventory->forEndpoint($endpoint) as $item) {
                $matches[] = $item;
            }
        }

        return $matches;
    }

    /**
     * @return array<int, OperationInventoryInterface>
     */
    public function inventories(): array
    {
        return $this->inventories;
    }
}
