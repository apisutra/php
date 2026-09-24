<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Core;

use ApiSutra\Enums\Http\HttpMethod;

interface RequestInterface
{
    /**
     * HTTP метод (из атрибута или переопределения)
     */
    public function getMethod(): HttpMethod;

    /**
     * Endpoint path (из атрибута или resolveEndpoint())
     */
    public function getEndpoint(): string;

    /**
     * Тип ответа (из #[Returns])
     */
    public function getResponseType(): ?string;
}
