<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\PolymorphicInput;

final readonly class ExternalOne implements ExternalEvent
{
    public function __construct(public string $label = 'one')
    {
    }
}
