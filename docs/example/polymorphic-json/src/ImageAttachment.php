<?php

declare(strict_types=1);

namespace Example\PolymorphicJson;

final readonly class ImageAttachment implements Attachment
{
    public function __construct(public string $url)
    {
    }
}
