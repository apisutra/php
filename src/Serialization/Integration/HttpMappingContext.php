<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Integration;

use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Request\PaginationOptions;
use ApiSutra\Request\RequestOptions;
use ApiSutra\VO\Http\ProviderResponse;

/** Снимок ссылок HTTP-вызова на момент входа в обработчик. */
final readonly class HttpMappingContext
{
    public function __construct(
        public string $traceId,
        public RequestInterface $request,
        public ?ProviderResponse $response,
        public RequestRole $role,
        public ?RequestOptions $options,
        public ?PaginationOptions $paginationOptions,
    ) {
    }
}
