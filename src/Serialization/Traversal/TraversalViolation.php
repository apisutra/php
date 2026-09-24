<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Traversal;

/** @internal Причина отказа; публичную ошибку выбирает исполнитель направления. */
enum TraversalViolation
{
    case Depth;
    case Cycle;
}
