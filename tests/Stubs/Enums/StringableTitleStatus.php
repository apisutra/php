<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Enums;

use Stringable;

final readonly class TitleString implements Stringable
{
    public function __construct(
        private string $value,
    ) {}

    public function __toString(): string
    {
        return $this->value;
    }
}

enum StringableTitleStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function title(): TitleString
    {
        return new TitleString(match ($this) {
            self::Active => 'Active Stringable',
            self::Inactive => 'Inactive Stringable',
        });
    }
}
