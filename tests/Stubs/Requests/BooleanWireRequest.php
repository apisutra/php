<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Casts\BooleanCast;
use ApiSutra\Casts\JsonCast;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Serialization\BooleanFormat;

#[Post('/booleans')]
class BooleanWireRequest extends AbstractRequest
{
    #[Query]
    public bool $default = false;

    #[Query]
    #[Cast(BooleanCast::class, BooleanFormat::Literal)]
    public bool $literal = false;

    #[Query]
    #[Cast(BooleanCast::class, BooleanFormat::Numeric)]
    public bool $numeric = true;

    /** @var array{nested: array{enabled: bool}} */
    #[Query]
    #[Cast(JsonCast::class)]
    public array $structure = ['nested' => ['enabled' => false]];

    #[Body]
    public bool $bodyBoolean = false;

    /** @var list<bool> */
    #[Body]
    public array $bodyArray = [false, true];

    #[Body]
    #[Cast(BooleanCast::class, BooleanFormat::Literal)]
    public bool $bodyLiteral = false;
}
