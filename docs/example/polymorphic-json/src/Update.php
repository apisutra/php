<?php

declare(strict_types=1);

namespace Example\PolymorphicJson;

use ApiSutra\Attributes\DataTransfer\DtoVariants;

#[DtoVariants('update_type', ['message' => MessageUpdate::class], unknown: UnknownUpdate::class)]
interface Update
{
}
