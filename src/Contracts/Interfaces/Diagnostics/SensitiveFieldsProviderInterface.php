<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Diagnostics;

/** Имена полей только диагностических копий данного запроса и ответа. */
interface SensitiveFieldsProviderInterface
{
    /** @return list<string> */
    public function sensitiveFields(): array;
}
