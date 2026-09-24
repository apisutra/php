<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

enum ScalarPolicy: string
{
    case Legacy = 'legacy';
    case Strict = 'strict';
}
