<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Http;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Post
{
    public function __construct(
        public string $path,
    ) {
    }
}
