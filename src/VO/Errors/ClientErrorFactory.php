<?php

declare(strict_types=1);

namespace ApiSutra\VO\Errors;

use ApiSutra\Collections\ErrorCollection;

/**
 * Фабрика клиентских ошибок для DX‑слоя.
 */
final readonly class ClientErrorFactory
{
    public function __construct(
        private ClientErrorMapperInterface $mapper,
    ) {
    }

    public function make(RequestError $error): ClientError
    {
        $mapped = $this->mapper->map($error);

        $systemContext = SystemErrorContextBuilder::build(
            traceId: SystemErrorContextBuilder::traceIdFromContext($error->context),
            httpStatus: $error->response?->status,
            requestClass: $error->requestClass,
            providerCode: $mapped->providerCode,
        );

        $context = array_merge(
            $systemContext,
            $error->context,
            $mapped->context,
        );

        return new ClientError(
            providerCode: $mapped->providerCode,
            sdkCode: $mapped->sdkCode,
            clientCode: $mapped->clientCode,
            appCode: $mapped->appCode,
            message: $mapped->message,
            messageDefinition: $mapped->messageDefinition(),
            context: $context,
            nested: $mapped->nested,
            requestClass: $mapped->requestClass ?? $error->requestClass,
        );
    }

    /**
     * @return array<int, ClientError>
     */
    public function makeMany(ErrorCollection $errors): array
    {
        return array_map(
            fn (RequestError $error): ClientError => $this->make($error),
            $errors->all(),
        );
    }
}
