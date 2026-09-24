<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Inventory;

/**
 * Резолвер resource-иерархии для request-класса.
 *
 * Возвращает упорядоченный путь top-level resource → sub-resource → ... .
 * Если resource определить нельзя, должен вернуть null (а не пустой массив).
 */
interface ResourceNameResolverInterface
{
    /**
     * @param  class-string         $requestClass
     * @return array<int, string>|null
     */
    public function resolveResourcePath(string $requestClass): ?array;
}
