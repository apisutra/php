<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Request;

enum CredentialsMergeMode: string
{
    case FillMissing = 'fill-missing';
    case Overwrite = 'overwrite';
    case FailOnConflict = 'fail-on-conflict';
}
