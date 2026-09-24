<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Localization;

final readonly class ParentDto
{
    public function __construct(public ChildDto $child)
    {
    }
}
