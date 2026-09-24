<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

enum ScalarType: string
{
    case Int = 'int';
    case Float = 'float';
    case Bool = 'bool';
    case String = 'string';
}
