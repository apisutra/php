<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Auth;

use Stringable;

interface AuthorizationParamsFormatterInterface
{
    /**
     * @param array<string, string|int|float|bool|Stringable> $params
     */
    public function format(array $params): string;
}
