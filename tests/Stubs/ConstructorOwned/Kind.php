<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ConstructorOwned;

enum Kind: string
{
    case Known = 'known';
    case Other = 'other';
}
