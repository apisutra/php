<?php

declare(strict_types=1);

namespace ApiSutra\VO\Errors;

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Enums\Errors\ErrorCode;

/**
 * Дефолтная стратегия маппинга ошибок.
 */
final readonly class DefaultClientErrorMapper implements ClientErrorMapperInterface
{
    public function map(RequestError $error): ClientError
    {
        $providerCode = $error->response?->status;

        return new ClientError(
            providerCode: $providerCode !== null ? (string) $providerCode : null,
            sdkCode: $error->code,
            clientCode: null,
            appCode: null,
            message: $error->message,
            messageDefinition: $error->messageDefinition(),
            context: $error->context,
            nested: $this->mapNestedErrors($error->nested),
            requestClass: $error->requestClass,
        );
    }

    public function status(ErrorCollection $errors): int
    {
        $first = $errors->first();
        if ($first === null) {
            return 500;
        }

        return match ($first->code) {
            ErrorCode::BadRequest => 400,
            ErrorCode::ClientError => $first->response->status ?? 400,
            ErrorCode::Unauthorized => 401,
            ErrorCode::Forbidden => 403,
            ErrorCode::NotFound => 404,
            ErrorCode::ValidationFailed => 422,
            ErrorCode::RateLimited => 429,
            ErrorCode::BadGateway => 502,
            ErrorCode::ServiceUnavailable => 503,
            ErrorCode::GatewayTimeout => 504,
            ErrorCode::Timeout => 504,
            ErrorCode::ConnectionFailed, ErrorCode::DnsError => 503,
            default => 500,
        };
    }

    /**
     * @param array<RequestError> $errors
     * @return array<ClientError>
     */
    private function mapNestedErrors(array $errors): array
    {
        $mapped = [];
        foreach ($errors as $error) {
            $mapped[] = $this->map($error);
        }

        return $mapped;
    }
}
