<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Hooks;

use Attribute;
use ApiSutra\Enums\Hooks\HookPriority;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
readonly class BeforeSend
{
    public function __construct(
        public string $handler,
        public HookPriority $priority = HookPriority::Normal,
        public ?string $name = null,
    ) {
    }
}
