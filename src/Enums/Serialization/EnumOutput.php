<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Serialization;

enum EnumOutput: string
{
    case Value = 'value';
    case Name = 'name';
    case Object = 'object';
    case TitleValueString = 'title_value_string';
}
