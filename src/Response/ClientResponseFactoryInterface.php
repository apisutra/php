<?php

declare(strict_types=1);

namespace ApiSutra\Response;

use ApiSutra\Result\ResolvedResultInterface;

/**
 * Контракт фабрики клиентского ответа.
 */
interface ClientResponseFactoryInterface
{
    public function make(ResolvedResultInterface $result): ClientResponse;
}
