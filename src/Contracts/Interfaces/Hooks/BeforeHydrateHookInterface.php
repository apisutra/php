<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Hooks;

use ApiSutra\VO\Pipeline\PipelineContext;

interface BeforeHydrateHookInterface extends HookInterface
{
    /**
     * @return array Модифицированные данные
     */
    public function handle(PipelineContext $context): array;
}
