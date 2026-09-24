<?php

declare(strict_types=1);

namespace Example\ResultErrors;

final readonly class AccountInfo
{
    public function __construct(public int $id)
    {
    }
}
