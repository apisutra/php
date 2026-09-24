<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\RateLimit;

use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\VO\Pipeline\PipelineContext;
use Closure;

final readonly class CallbackHook implements HookInterface
{
    /** @param Closure(PipelineContext): void $callback */
    public function __construct(private Closure $callback)
    {
    }
    public function handle(PipelineContext $context): ?array
    {
        ($this->callback)($context);
        return null;
    }
}
