<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Enums;

enum TitleStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function title(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }
}
