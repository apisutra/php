<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Execution;

enum ExecutionMode: string
{
    case Sequential = 'sequential';
    case Parallel = 'parallel';
}
