<?php

declare(strict_types=1);

namespace ApiSutra\OperationInventory;

use ApiSutra\Contracts\Interfaces\Inventory\OperationInventoryInterface;

final readonly class OperationInventory implements OperationInventoryInterface
{
    /**
     * @var array<string, OperationDescriptorView>
     */
    private array $byRequestClass;

    /**
     * @param iterable<OperationDescriptorView> $items
     */
    public function __construct(iterable $items)
    {
        $indexed = [];

        foreach ($items as $item) {
            $indexed[$item->requestClass] = $item;
        }

        $this->byRequestClass = $indexed;
    }

    /**
     * @return array<int, OperationDescriptorView>
     */
    #[\Override]
    public function all(): array
    {
        return array_values($this->byRequestClass);
    }

    #[\Override]
    public function forRequest(string $requestClass): ?OperationDescriptorView
    {
        return $this->byRequestClass[$requestClass] ?? null;
    }

    /**
     * @return array<int, OperationDescriptorView>
     */
    #[\Override]
    public function forEndpoint(string $endpoint): array
    {
        return array_values(array_filter(
            $this->byRequestClass,
            static fn (OperationDescriptorView $item): bool => $item->endpoint === $endpoint,
        ));
    }
}
