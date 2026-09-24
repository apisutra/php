<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\Attributes\DataTransfer\To;

final class ReceiverOutput
{
    #[Extras] #[To("values")] public array $_extra = [];
}
