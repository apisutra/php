<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MetadataIsolation;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Header;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Core\AbstractRequest;

#[Get('/items/{id}')]
final class HeaderPathRequest extends AbstractRequest
{
    #[Header('X-Number')]
    #[Cast(CountingCast::class, new MutableCounter())]
    public int $header = 0;

    #[Path('id')]
    #[Cast(CountingCast::class, new MutableCounter())]
    public int $id = 0;
}
