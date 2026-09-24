<?php

declare(strict_types=1);

namespace ApiSutra\Execution\Batch;

use ApiSutra\Contracts\Interfaces\Core\RequestInterface;

/**
 * Контракт резолвера элемента входного списка запросов.
 */
interface RequestResolverInterface
{
    public function supports(mixed $item): bool;

    public function resolve(mixed $item, BatchContext $context): ?RequestInterface;
}
