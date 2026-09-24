<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hooks;

use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\VO\Pipeline\PipelineContext;

final class NamedHook implements HookInterface
{
    public function __construct(
        private string $name,
    ) {}

    public function handle(PipelineContext $context): ?array
    {
        HookRecorder::add($this->name);
        return null;
    }
}
