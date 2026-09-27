<?php

declare(strict_types=1);

namespace Example\PolymorphicJson;

use ApiSutra\Attributes\DataTransfer\DtoVariants;

#[DtoVariants('type', ['image' => ImageAttachment::class, 'share' => ShareAttachment::class], unknown: RawAttachment::class)]
interface Attachment
{
}
