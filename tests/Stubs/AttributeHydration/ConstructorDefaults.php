<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\ConstructorValue;
use ApiSutra\Attributes\DataTransfer\DefaultValue;

final readonly class ConstructorDefaults
{
    #[ConstructorValue(allowMissing: true)]
    #[DefaultValue(value: 'record')]
    public string $kind;

    public function __construct()
    {
        $this->kind = 'record';
    }
}
