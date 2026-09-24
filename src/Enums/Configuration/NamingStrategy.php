<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Configuration;

enum NamingStrategy: string
{
    case None = 'none';
    case SnakeCase = 'snake_case';
}
