<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Pagination;

use ApiSutra\Contracts\Interfaces\Core\TimeoutAwareTransportInterface;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Http\TransportOptions;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Override;

/** Генерирует страницы без истории запросов и результатов для проверки памяти обхода. */
final class NonRecordingPageTransport implements TimeoutAwareTransportInterface
{
    public int $sent = 0;

    public function __construct(private readonly int $pages)
    {
    }

    #[Override]
    public function send(PreparedRequest $request): ProviderResponse
    {
        $page = ++$this->sent;
        $body = json_encode([
            'data' => [str_pad((string) $page, 16384, 'x')],
            'meta' => ['page' => $page, 'has_more' => $page < $this->pages],
        ], JSON_THROW_ON_ERROR);
        return new ProviderResponse(200, ['Content-Type' => ['application/json']], $body, $request, 0);
    }

    #[Override]
    public function sendAsync(PreparedRequest $request): PromiseInterface
    {
        return new FulfilledPromise($this->send($request));
    }

    #[Override]
    public function assertSupportsTimeouts(TransportOptions $options): void
    {
    }
}
