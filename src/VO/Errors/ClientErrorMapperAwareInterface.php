<?php

declare(strict_types=1);

namespace ApiSutra\VO\Errors;

/**
 * Контракт фабрики, принимающей mapper ошибок.
 */
interface ClientErrorMapperAwareInterface
{
    public function withErrorMapper(ClientErrorMapperInterface $mapper): static;
}
