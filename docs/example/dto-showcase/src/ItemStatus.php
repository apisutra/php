<?php

declare(strict_types=1);

namespace Example\DtoShowcase;

enum ItemStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
}
