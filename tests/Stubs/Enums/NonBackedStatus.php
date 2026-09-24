<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Enums;

enum NonBackedStatus
{
    case Active;
    case Inactive;

    public function title(): string
    {
        return match ($this) {
            self::Active => 'Active Title',
            self::Inactive => 'Inactive Title',
        };
    }
}
