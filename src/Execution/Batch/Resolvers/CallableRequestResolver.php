<?php

declare(strict_types=1);

namespace ApiSutra\Execution\Batch\Resolvers;

use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Execution\Batch\BatchContext;
use ApiSutra\Execution\Batch\RequestResolverInterface;

/**
 * Резолвер запросов из callable.
 */
final class CallableRequestResolver implements RequestResolverInterface
{
    public function supports(mixed $item): bool
    {
        return is_callable($item);
    }

    public function resolve(mixed $item, BatchContext $context): ?RequestInterface
    {
        $request = $item($context->parent?->request);

        return $request instanceof RequestInterface ? $request : null;
    }
}
