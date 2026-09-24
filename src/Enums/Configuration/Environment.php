<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Configuration;

enum Environment: string
{
    case Local = 'local';
    case Testing = 'testing';
    case Staging = 'staging';
    case Production = 'production';
}
