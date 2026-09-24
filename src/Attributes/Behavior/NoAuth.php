<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Behavior;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class NoAuth
{
    public function __construct()
    {
    }
}
