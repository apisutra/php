<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Request;

use Attribute;
use ApiSutra\Enums\Request\RequestUnmappedTarget;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class RequestDefaults
{
    public function __construct(
        public RequestUnmappedTarget $unmapped = RequestUnmappedTarget::Convention,
    ) {
    }
}
