<?php

declare(strict_types=1);

namespace ApiSutra\DataTransfer;

use ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use ApiSutra\VO\Pipeline\PipelineContext;

abstract readonly class AbstractResponseDto extends AbstractDto implements ResponseDtoInterface
{
    /**
     * Вычисляемые поля перед гидрацией
     * Context nullable для поддержки from() без context
     */
    #[\Override]
    public static function computed(array $data, ?PipelineContext $context = null): array
    {
        return $data;
    }
}
