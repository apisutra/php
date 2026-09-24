<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Auth;

/**
 * Переопределение поведения auth на уровне запроса.
 */
enum AuthOverride
{
    case Enable;
    case Disable;
    case ForceEnable;
}
