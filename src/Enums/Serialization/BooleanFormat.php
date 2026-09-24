<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Serialization;

enum BooleanFormat: string
{
    case Numeric = 'numeric';
    case Literal = 'literal';

    public function format(bool $value): string
    {
        return match ($this) {
            self::Numeric => $value ? '1' : '0',
            self::Literal => $value ? 'true' : 'false',
        };
    }
}
