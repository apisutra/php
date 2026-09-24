<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\Extras;

final class TwoReceivers
{
    #[Extras] public array $a = [];
    #[Extras] public array $b = [];
}
