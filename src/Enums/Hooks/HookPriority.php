<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Hooks;

enum HookPriority: string
{
    case First = 'first';
    case Normal = 'normal';
    case Last = 'last';
}
