<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Continuation;

enum ContinuationStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
}
