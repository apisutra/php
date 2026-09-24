<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Response;

use Attribute;

/** Вернуть строку тела без декодирования и гидрации. */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class RawResponse
{
}
