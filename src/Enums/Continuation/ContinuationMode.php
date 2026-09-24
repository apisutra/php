<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Continuation;

enum ContinuationMode: string
{
    case Auto = 'auto';
    case Sync = 'sync';
    case Async = 'async';
}
