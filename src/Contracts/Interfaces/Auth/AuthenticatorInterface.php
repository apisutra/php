<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Auth;

use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use ApiSutra\VO\Http\PreparedRequest;

interface AuthenticatorInterface
{
    /**
     * Добавить auth данные к запросу
     */
    public function authenticate(PreparedRequest $request): PreparedRequest;

    /**
     * Нужен ли refresh токена (до запроса)
     */
    public function shouldRefresh(): bool;

    /**
     * Запрос на refresh токена
     */
    public function getRefreshRequest(): ?RequestInterface;

    /**
     * Обработка ответа refresh
     */
    public function processTokenResponse(ResponseDtoInterface $response): void;
}
