<?php

declare(strict_types=1);

namespace ApiSutra\VO\Errors;

/**
 * Фабрика типизированного контекста ошибки.
 */
interface ErrorContextFactoryInterface
{
    public function make(ClientError $error): ?object;
}
