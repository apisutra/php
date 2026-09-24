<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Attributes\DataTransfer\DateTimeTo;
use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\BodyRoot;
use ApiSutra\Attributes\Request\File;
use ApiSutra\Attributes\Request\Header;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Attributes\Request\Query;

final readonly class ReceiverOutputAttributesDto
{
    public function __construct(
        #[To('out')] public array $to = [],
        #[DateTimeTo] public array $date = [],
        #[Query] public array $query = [],
        #[Body] public array $body = [],
        #[BodyRoot] public array $root = [],
        #[Header('X-Value')] public array $header = [],
        #[Path('value')] public array $path = [],
        #[File] public array $file = [],
    ) {
    }
}
