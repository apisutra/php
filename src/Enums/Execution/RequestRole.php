<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Execution;

enum RequestRole: string
{
    case Root = 'root';
    case Nested = 'nested';
    case Dependency = 'dependency';
}
