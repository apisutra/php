<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Preparation;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Request\RequestOptions;
use ApiSutra\VO\Http\PreparedRequest;

final readonly class RequestPreparer
{
    public function __construct(
        private ClientConfig $config,
    ) {
    }

    public function resolveTraceId(
        RequestInterface $request,
        ?string $traceId,
        ?string $pipelineTraceId,
        ?RequestOptions $options = null,
    ): string {
        $override = $this->resolveRequestTraceId($request, $options);
        if ($override !== null) {
            return $override;
        }

        $provided = $this->resolveProvidedTraceId($traceId, $pipelineTraceId);
        if ($provided !== null) {
            return $provided;
        }

        return $this->generateTraceId();
    }

    public function applyRequestOverrides(
        RequestInterface $request,
        PreparedRequest $prepared,
        ?RequestOptions $options = null,
    ): PreparedRequest {
        if (!$request instanceof AbstractRequest) {
            return $prepared;
        }

        $headers = $this->mergeHeaders($prepared, $request, $options);
        $headers = $this->applyIdempotencyHeader($request, $headers, $options);

        return $prepared->with(headers: $headers);
    }

    private function resolveRequestTraceId(RequestInterface $request, ?RequestOptions $options): ?string
    {
        if ($options?->getTraceIdOverride() !== null) {
            return $options->getTraceIdOverride();
        }

        if ($request instanceof AbstractRequest) {
            return $request->getTraceIdOverride();
        }

        return null;
    }

    private function resolveProvidedTraceId(?string $traceId, ?string $pipelineTraceId): ?string
    {
        return $traceId ?? $pipelineTraceId;
    }

    private function generateTraceId(): string
    {
        $bytes = random_bytes(16);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8)
            . '-' . substr($hex, 8, 4)
            . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4)
            . '-' . substr($hex, 20, 12);
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function applyIdempotencyHeader(
        AbstractRequest $request,
        array $headers,
        ?RequestOptions $options,
    ): array {
        $idempotency = $request->getIdempotentAttribute();
        $idempotencyKey = $options?->getIdempotencyKey() ?? $request->getIdempotencyKey();
        if ($idempotency !== null) {
            $header = $idempotency->header ?? $this->config->idempotencyHeader;
            foreach ($headers as $name => $value) {
                if (strcasecmp($name, $header) === 0) {
                    $idempotencyKey ??= $value;
                    unset($headers[$name]);
                }
            }
            $headers[$header] = $idempotencyKey ?? $this->generateIdempotencyKey();
        } elseif ($idempotencyKey !== null) {
            $headers[$this->config->idempotencyHeader] = $idempotencyKey;
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    private function mergeHeaders(
        PreparedRequest $prepared,
        AbstractRequest $request,
        ?RequestOptions $options,
    ): array {
        $overrideHeaders = $options?->getHeadersOverride() ?? $request->getHeadersOverride();

        return array_merge($prepared->headers, $overrideHeaders);
    }

    private function generateIdempotencyKey(): string
    {
        // Ключ принадлежит одному выполнению и не зависит от сериализации тела или точности часов.
        return bin2hex(random_bytes(32));
    }
}
