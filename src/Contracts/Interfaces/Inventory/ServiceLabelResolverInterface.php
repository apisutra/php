<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Inventory;

/**
 * Презентационный резолвер: даёт человекочитаемый label для сервис-клиента.
 *
 * Используется ТОЛЬКО exporter-ами для красивых заголовков (например,
 * `KonturRealtyClient` → "Realty"). В данных каталога (ResponseDtoUsage)
 * хранится сырой FQCN — label это исключительно view concern.
 */
interface ServiceLabelResolverInterface
{
    /**
     * @param class-string $serviceClass
     */
    public function resolve(string $serviceClass): string;
}
