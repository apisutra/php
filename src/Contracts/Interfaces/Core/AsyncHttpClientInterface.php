<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Core;

use ApiSutra\VO\Http\TransportOptions;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

/** HTTP Promise продвигается в цикле SDK и поддерживает отмену. */
interface AsyncHttpClientInterface extends HttpClientOptionsInterface
{
    public function assertSupportsConcurrency(): void;

    public function sendAsyncWithOptions(RequestInterface $request, TransportOptions $options): PromiseInterface;
}
