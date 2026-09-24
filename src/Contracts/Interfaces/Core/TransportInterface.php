<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Core;

use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use GuzzleHttp\Promise\PromiseInterface;

interface TransportInterface
{
    /**
     * Синхронная отправка запроса
     */
    public function send(PreparedRequest $request): ProviderResponse;

    /**
     * Асинхронная отправка запроса
     */
    public function sendAsync(PreparedRequest $request): PromiseInterface;
}
