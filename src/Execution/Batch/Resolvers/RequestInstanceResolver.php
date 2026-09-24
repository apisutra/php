<?php

declare(strict_types=1);

namespace ApiSutra\Execution\Batch\Resolvers;

use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Execution\Batch\BatchContext;
use ApiSutra\Execution\Batch\RequestResolverInterface;

/**
 * Резолвер готовых инстансов запросов.
 */
final class RequestInstanceResolver implements RequestResolverInterface
{
    public function supports(mixed $item): bool
    {
        return $item instanceof RequestInterface;
    }

    public function resolve(mixed $item, BatchContext $context): ?RequestInterface
    {
        return $item;
    }
}
