<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Execution;

enum FailStrategy: string
{
    case FailAll = 'fail_all';
    case Partial = 'partial';
    case IgnoreErrors = 'ignore';
}
