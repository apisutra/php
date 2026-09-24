<?php

declare(strict_types=1);

namespace ApiSutra\Core;

use ApiSutra\Contracts\Interfaces\Core\ClientInterface;

abstract class AbstractResource
{
    public function __construct(
        protected readonly ClientInterface $client,
    ) {
    }

    /**
     * Создать вложенный ресурс
     */
    protected function resource(string $class, mixed ...$args): AbstractResource
    {
        return new $class($this->client, ...$args);
    }

    /**
     * Создать запрос с привязкой к клиенту
     */
    protected function request(string $class, mixed ...$args): AbstractRequest
    {
        $request = new $class(...$args);
        $request->setClient($this->client);

        return $request;
    }
}
