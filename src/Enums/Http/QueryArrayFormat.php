<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Http;

enum QueryArrayFormat: string
{
    case Brackets = 'brackets';
    case Indices = 'indices';
    case Comma = 'comma';
    case Repeat = 'repeat';
}
