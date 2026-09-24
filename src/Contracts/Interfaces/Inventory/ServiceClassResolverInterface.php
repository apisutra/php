<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Inventory;

/**
 * Резолвит FQCN сервис-клиента, к которому относится request-класс.
 *
 * Используется ResponseDtoCatalog в multi-service сценарии для проставления
 * ResponseDtoUsage::$serviceClass. Для single-client сценария резолвер не
 * нужен — в каталоге serviceClass просто остаётся null.
 */
interface ServiceClassResolverInterface
{
    /**
     * @param  class-string $requestClass
     * @return class-string|null
     */
    public function resolve(string $requestClass): ?string;
}
