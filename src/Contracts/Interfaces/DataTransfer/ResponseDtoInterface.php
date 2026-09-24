<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\DataTransfer;

use ApiSutra\VO\Pipeline\PipelineContext;

interface ResponseDtoInterface extends DtoInterface
{
    /**
     * Вычисляемые поля перед гидрацией
     * Context nullable для поддержки from() без context
     */
    public static function computed(array $data, ?PipelineContext $context = null): array;
}
