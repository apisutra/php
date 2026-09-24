<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support\Result;

use ApiSutra\Contracts\Interfaces\DataTransfer\ResultMeta;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResultMetaExtractorInterface;

final class ProviderEnvelopeMetaExtractor implements ResultMetaExtractorInterface
{
    public int $calls = 0;

    #[\Override]
    public function extract(ExecutionResult $result): ?ResultMeta
    {
        $this->calls++;
        $payload = $this->resolvePayload($result);

        if (!is_array($payload)) {
            return null;
        }

        $code = $this->normalizeInt($payload['resultCode'] ?? null);
        $message = is_string($payload['resultMessage'] ?? null) ? $payload['resultMessage'] : null;
        $token = is_string($payload['operationToken'] ?? null) ? trim($payload['operationToken']) : null;
        $token = $token === '' ? null : $token;

        if ($code === null && $message === null && $token === null) {
            return null;
        }

        return new ProviderEnvelopeMeta(
            resultCode: $code,
            resultMessage: $message,
            operationToken: $token,
        );
    }

    private function resolvePayload(ExecutionResult $result): ?array
    {
        $rawPayload = $result->response?->json();

        if (is_array($rawPayload)) {
            return $rawPayload;
        }

        $errorPayload = $result->errors->first()?->response?->json();

        if (is_array($errorPayload)) {
            return $errorPayload;
        }

        return is_array($result->data) ? $result->data : null;
    }

    private function normalizeInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
