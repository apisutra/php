<?php

declare(strict_types=1);

namespace ApiSutra\VO\Errors;

use ApiSutra\Collections\ErrorCollection;

/**
 * Контракт стратегии маппинга ошибок.
 */
interface ClientErrorMapperInterface
{
    public function map(RequestError $error): ClientError;

    public function status(ErrorCollection $errors): int;
}
