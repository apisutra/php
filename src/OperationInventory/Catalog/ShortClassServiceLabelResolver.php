<?php

declare(strict_types=1);

namespace ApiSutra\OperationInventory\Catalog;

use ApiSutra\Contracts\Interfaces\Inventory\ServiceLabelResolverInterface;

/**
 * Дефолтный label-резолвер: берёт last segment FQCN.
 *
 * Например: Vendor\\Kontur\\RealtyClient → "RealtyClient".
 */
final readonly class ShortClassServiceLabelResolver implements ServiceLabelResolverInterface
{
    #[\Override]
    public function resolve(string $serviceClass): string
    {
        $position = strrpos($serviceClass, '\\');

        return $position === false ? $serviceClass : substr($serviceClass, $position + 1);
    }
}
