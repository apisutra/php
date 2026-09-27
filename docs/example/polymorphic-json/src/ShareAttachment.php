<?php

declare(strict_types=1);

namespace Example\PolymorphicJson;

use ApiSutra\Attributes\DataTransfer\Nested;

final readonly class ShareAttachment implements Attachment
{
    public function __construct(#[Nested] public SharePayload $payload)
    {
    }
}
