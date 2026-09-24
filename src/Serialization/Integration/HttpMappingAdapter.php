<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Integration;

use ApiSutra\VO\Pipeline\PipelineContext;

/** @internal HTTP-зависимость остаётся за границей направленных контекстов. */
final class HttpMappingAdapter
{
    /** @return array<class-string, object> */
    public static function extensions(?PipelineContext $context): array
    {
        return $context === null ? [] : [HttpMappingContext::class => new HttpMappingContext(
            $context->traceId,
            $context->request,
            $context->response,
            $context->role,
            $context->options,
            $context->paginationOptions,
        )];
    }
}
