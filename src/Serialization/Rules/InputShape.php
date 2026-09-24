<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

enum InputShape: string
{
    case List = 'list';
    case Object = 'object';
}
