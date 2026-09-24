<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Core;

use ApiSutra\Collections\RequestCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\VO\Pipeline\PipelineContext;

interface CompositeRequestInterface extends RequestInterface
{
    /**
     * Дочерние запросы для выполнения
     */
    public function requests(): RequestCollection;

    /**
     * Агрегация результатов в единый DTO
     */
    public function aggregate(ResultCollection $results, PipelineContext $ctx): mixed;
}
