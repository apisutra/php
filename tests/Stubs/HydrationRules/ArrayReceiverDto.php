<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

final readonly class ArrayReceiverDto
{
    public function __construct(public array $extra = [])
    {
    }
    public function toArray(): array
    {
        return ['custom' => $this->extra];
    }
}
