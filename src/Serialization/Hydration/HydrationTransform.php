<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Hydration;

/** @internal Операция поля; порядок стадий закрыт и не задаётся приложением. */
enum HydrationTransform
{
    case Cast;
    case Shape;
    case Identity;
    case Nested;
    case Builtin;
}
