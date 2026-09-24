<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Auth;

use ApiSutra\VO\Http\PreparedRequest;
use Stringable;

interface AuthorizationParamsProviderInterface
{
    /**
     * Сгенерировать параметры для Authorization.
     *
     * @return array<string, string|int|float|bool|Stringable|null>
     */
    public function resolve(PreparedRequest $request): array;
}
