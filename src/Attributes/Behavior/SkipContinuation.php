<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Behavior;

use Attribute;

/** Служебный запрос не принимает continuation-параметры API клиента. */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class SkipContinuation
{
}
