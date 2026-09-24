<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Attributes;

enum AttributeContextType: string
{
    case Request = 'request';
    case Dto = 'dto';
}
