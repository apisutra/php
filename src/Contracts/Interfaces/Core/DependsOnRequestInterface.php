<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Core;

use ApiSutra\Collections\RequestCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\VO\Pipeline\PipelineContext;

interface DependsOnRequestInterface extends RequestInterface
{
    /**
     * Запросы-зависимости
     */
    public function dependencies(): RequestCollection;

    /**
     * Обработка результатов зависимостей перед основным запросом
     */
    public function processDependencies(ResultCollection $results, PipelineContext $ctx): void;
}
