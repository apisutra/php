<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MetadataIsolation;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Get('/cast')]
#[Returns(CastDto::class)]
final class CastRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        #[Cast(CountingCast::class, new MutableCounter())]
        public int $number = 0,
    ) {
    }
}
