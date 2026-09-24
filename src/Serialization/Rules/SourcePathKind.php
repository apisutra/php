<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

enum SourcePathKind: string
{
    case Resolved = 'resolved';
    case Expected = 'expected';
    case Boundary = 'boundary';
    case Unavailable = 'unavailable';
}
