<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

enum ContainerShape: string
{
    case List = 'list';
    case Object = 'object';
}
