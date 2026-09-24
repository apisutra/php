<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Diagnostics;

/** @internal Состояние принадлежит одному вызову, а не клиенту. */
enum ExecutionState
{
    case Created;
    case Running;
    case Finalized;
}
