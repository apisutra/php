<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Continuation;

use ApiSutra\Continuation\ContinuationContext;

final readonly class ContextFinalDto
{
    public function __construct(public ContinuationContext $context)
    {
    }
}
