<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Core;

interface ClientResolverInterface
{
    /**
     * Разрешить клиента для запроса.
     *
     * Реализация должна корректно работать с execution‑обёртками,
     * возвращая клиента для исходного запроса.
     */
    public function resolve(RequestInterface $request): ClientInterface;

    /**
     * Проверить, что запрос принадлежит указанному клиенту.
     *
     * Нужен для защиты от использования "чужих" запросов в клиенте.
     */
    public function assertOwnership(ClientInterface $client, RequestInterface $request): void;
}
