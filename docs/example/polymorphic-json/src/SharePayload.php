<?php

declare(strict_types=1);

namespace Example\PolymorphicJson;

final readonly class SharePayload
{
    public function __construct(public ?string $url = null)
    {
    }
}
