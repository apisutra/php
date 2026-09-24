<?php

declare(strict_types=1);

namespace ApiSutra\Resolver;

use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Core\ClientResolverInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;

/**
 * Резолвер клиента по классу запроса.
 *
 * Учитывает RequestExecutionInterface, чтобы работать с цепочками
 * исполнения, возвращая "владельца" исходного запроса.
 */
final readonly class ClientResolver implements ClientResolverInterface
{
    public function __construct(
        private ClientRegistry $registry,
    ) {
    }

    /**
     * Разрешить клиента для запроса или цепочки исполнения.
     */
    #[\Override]
    public function resolve(RequestInterface $request): ClientInterface
    {
        $requestClass = $this->resolveRequestClass($request);
        return $this->registry->resolve($requestClass);
    }

    /**
     * Проверить, что запрос принадлежит указанному клиенту.
     */
    #[\Override]
    public function assertOwnership(ClientInterface $client, RequestInterface $request): void
    {
        $requestClass = $this->resolveRequestClass($request);
        $this->registry->assertOwnership($client, $requestClass);
    }

    /**
     * Получить класс исходного запроса, разворачивая execution‑обёртки.
     */
    private function resolveRequestClass(RequestInterface $request): string
    {
        if ($request instanceof RequestExecutionInterface) {
            $request = $request->getRequest();
        }

        return $request::class;
    }
}
