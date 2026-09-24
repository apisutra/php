<?php

declare(strict_types=1);

namespace ApiSutra\VO\Http;

use ApiSutra\Localization\Message;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Http\RequestDestination;
use ApiSutra\VO\Files\FileTransferOptions;
use Psr\Http\Message\StreamInterface;

readonly class PreparedRequest
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public HttpMethod $method,
        public string $url,
        public array $headers = [],
        public ?string $body = null,
        public ?StreamInterface $stream = null,
        public array $meta = [],
        public ?TransportOptions $transportOptions = null,
        public ?RequestDestination $destination = null,
        public ?FileTransferOptions $fileTransfer = null,
    ) {
        if ($body !== null && $stream !== null) {
            throw new ConfigurationException(new Message('vo.http_body_cannot_contain_both_a_string_and_a'));
        }
    }

    /**
     * Null сохраняет прежнее значение; выбор body/stream, отличный от null, заменяет всё тело.
     *
     * @param array<string, string>|null $headers Полный новый снимок заголовков
     * @param array<string, mixed>|null $meta
     */
    public function with(
        ?string $url = null,
        ?array $headers = null,
        ?string $body = null,
        ?StreamInterface $stream = null,
        ?array $meta = null,
        ?TransportOptions $transportOptions = null,
        ?RequestDestination $destination = null,
        ?FileTransferOptions $fileTransfer = null,
    ): self {
        return $this->copy(
            replaceBody: $body !== null || $stream !== null,
            body: $body,
            stream: $stream,
            url: $url,
            headers: $headers,
            meta: $meta,
            transportOptions: $transportOptions,
            destination: $destination,
            fileTransfer: $fileTransfer,
        );
    }

    public function withBody(string $body): self
    {
        return $this->with(body: $body);
    }

    public function withStream(StreamInterface $stream): self
    {
        return $this->with(stream: $stream);
    }

    public function withoutBody(): self
    {
        return $this->copy(replaceBody: true);
    }

    /**
     * @param array<string, string>|null $headers
     * @param array<string, mixed>|null $meta
     */
    private function copy(
        bool $replaceBody,
        ?string $body = null,
        ?StreamInterface $stream = null,
        ?string $url = null,
        ?array $headers = null,
        ?array $meta = null,
        ?TransportOptions $transportOptions = null,
        ?RequestDestination $destination = null,
        ?FileTransferOptions $fileTransfer = null,
    ): self {
        $resolvedHeaders = $headers ?? $this->headers;
        $resolvedMeta = $meta ?? $this->meta;
        if ($replaceBody) {
            if ($headers === null) {
                foreach ($resolvedHeaders as $name => $value) {
                    if (in_array(strtolower($name), ['content-length', 'transfer-encoding'], true)) {
                        unset($resolvedHeaders[$name]);
                    }
                }
            }
            // Снимок сериализатора больше не описывает выбранное HTTP-тело.
            unset($resolvedMeta['body'], $resolvedMeta['bodyIsRoot']);
        }

        return new self(
            method: $this->method,
            url: $url ?? $this->url,
            headers: $resolvedHeaders,
            body: $replaceBody ? $body : $this->body,
            stream: $replaceBody ? $stream : $this->stream,
            meta: $resolvedMeta,
            transportOptions: $transportOptions ?? $this->transportOptions,
            destination: $destination ?? $this->destination,
            fileTransfer: $fileTransfer ?? $this->fileTransfer,
        );
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return $this->with(headers: $headers);
    }
}
