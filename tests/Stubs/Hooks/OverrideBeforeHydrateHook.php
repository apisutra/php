<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hooks;

use ApiSutra\Contracts\Interfaces\Hooks\BeforeHydrateHookInterface;
use ApiSutra\VO\Pipeline\PipelineContext;

final class OverrideBeforeHydrateHook implements BeforeHydrateHookInterface
{
    public function handle(PipelineContext $context): array
    {
        return [
            'id' => 99,
            'name' => 'override',
        ];
    }
}
