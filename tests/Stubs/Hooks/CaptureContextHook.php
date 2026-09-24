<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hooks;

use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\VO\Pipeline\PipelineContext;

final class CaptureContextHook implements HookInterface
{
    public static ?PipelineContext $context = null;

    public static function reset(): void
    {
        self::$context = null;
    }

    public function handle(PipelineContext $context): ?array
    {
        self::$context = $context;
        return null;
    }
}
