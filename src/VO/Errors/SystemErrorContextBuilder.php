<?php

declare(strict_types=1);

namespace ApiSutra\VO\Errors;

use ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestOptionsProviderInterface;
use ApiSutra\Core\AbstractRequest;

/**
 * Построение системного контекста ошибок.
 */
final class SystemErrorContextBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function build(
        ?string $traceId,
        ?int $httpStatus,
        ?string $requestClass,
        ?string $providerCode = null,
    ): array {
        $context = [];

        if ($traceId !== null) {
            $context[SystemErrorContextKeys::TraceId->value] = $traceId;
        }

        if ($httpStatus !== null) {
            $context[SystemErrorContextKeys::HttpStatus->value] = $httpStatus;
        }

        if ($requestClass !== null) {
            $context[SystemErrorContextKeys::RequestClass->value] = $requestClass;
        }

        if ($providerCode !== null) {
            $context[SystemErrorContextKeys::ProviderCode->value] = $providerCode;
        }

        return $context;
    }

    public static function resolveTraceId(RequestInterface $request): ?string
    {
        if ($request instanceof RequestOptionsProviderInterface) {
            $override = $request->getOptions()->getTraceIdOverride();
            if ($override !== null) {
                return $override;
            }

            if ($request instanceof RequestExecutionInterface) {
                $request = $request->getRequest();
            }
        }

        if ($request instanceof AbstractRequest) {
            return $request->getContext()?->traceId;
        }

        return null;
    }

    public static function traceIdFromContext(array $context): ?string
    {
        $value = $context[SystemErrorContextKeys::TraceId->value] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
