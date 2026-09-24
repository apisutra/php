<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MetadataIsolation;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;

#[Get('/values')]
final class ValuesRequest extends AbstractRequest
{
    public function __construct(#[Query] public int $number = 0)
    {
    }
}
