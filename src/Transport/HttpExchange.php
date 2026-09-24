<?php

declare(strict_types=1);

namespace ApiSutra\Transport;

use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Http\TransportOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/** @internal Общие для sync/async преобразование ответа и владение sink одной попытки. */
final class HttpExchange
{
    public function __construct(
        private ?PreparedRequest $prepared,
        public readonly RequestInterface $request,
        public readonly ?TransportOptions $options,
        private readonly ?StreamInterface $sink,
        private readonly float $started,
    ) {
    }

    public function complete(ResponseInterface $response): ProviderResponse
    {
        if ($this->sink !== null) {
            if ($response->getBody() !== $this->options?->sink) {
                throw new ConfigurationException(new Message('transport.http_client_did_not_return_a_managed_download_stream'));
            }
            $this->sink->rewind();
        }
        return new ProviderResponse(
            $response->getStatusCode(),
            $response->getHeaders(),
            $this->sink === null ? (string) $response->getBody() : null,
            $this->prepared,
            (microtime(true) - $this->started) * 1000,
            $this->sink,
        );
    }

    public function close(): void
    {
        $this->sink?->close();
        $this->prepared = null;
    }
}
