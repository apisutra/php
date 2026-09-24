<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Pagination;

use ApiSutra\Config\PaginationConfig;
use ApiSutra\VO\Metadata\PaginationMeta;
use ApiSutra\VO\Pipeline\PipelineContext;

interface PaginationMetaOverrideInterface
{
    /**
     * @param array<string, mixed> $response Ответ провайдера
     * @param array<string, mixed> $meta Извлечённая meta-часть ответа
     */
    public function resolvePaginationMeta(
        array $response,
        array $meta,
        PaginationConfig $config,
        ?PipelineContext $context
    ): PaginationMeta;
}
