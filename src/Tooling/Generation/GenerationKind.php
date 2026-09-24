<?php

declare(strict_types=1);

namespace ApiSutra\Tooling\Generation;

enum GenerationKind: string
{
    case Client = 'client';
    case Request = 'request';
    case Dto = 'dto';
}
