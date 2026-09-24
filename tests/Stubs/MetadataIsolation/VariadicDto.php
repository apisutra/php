<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MetadataIsolation;

final readonly class VariadicDto
{
    /** @var list<int> */
    public array $numbers;

    public function __construct(int ...$numbers)
    {
        $this->numbers = $numbers;
    }
}
