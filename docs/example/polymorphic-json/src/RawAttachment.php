<?php

declare(strict_types=1);

namespace Example\PolymorphicJson;

use ApiSutra\Attributes\DataTransfer\Extras;

final readonly class RawAttachment implements Attachment
{
    /** @param array<array-key, mixed> $raw */
    public function __construct(#[Extras] public array $raw = [])
    {
    }
}
