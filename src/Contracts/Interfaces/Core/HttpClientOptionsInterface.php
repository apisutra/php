<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Core;

use ApiSutra\VO\Http\TransportOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** Дополнительный контракт адаптера PSR-18 для опций отдельной отправки. */
interface HttpClientOptionsInterface
{
    public function assertSupportsTimeouts(TransportOptions $options): void;
    public function sendWithOptions(RequestInterface $request, TransportOptions $options): ResponseInterface;
}
