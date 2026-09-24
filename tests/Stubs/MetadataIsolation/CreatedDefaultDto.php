<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MetadataIsolation;

final readonly class CreatedDefaultDto
{
    public function __construct(public ?CreatedValue $state = new CreatedValue())
    {
    }
}
