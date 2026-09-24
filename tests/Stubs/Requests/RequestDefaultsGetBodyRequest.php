<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\Header;
use ApiSutra\Attributes\Request\Ignore;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Request\RequestDefaults;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Request\RequestUnmappedTarget;

#[Get('/defaults/{id}')]
#[RequestDefaults(unmapped: RequestUnmappedTarget::Body)]
final class RequestDefaultsGetBodyRequest extends AbstractRequest
{
    public function __construct(
        #[Path('id')]
        public string $id,
        public string $plain,
        #[Query('q')]
        public string $query,
        #[Body('payload.explicit')]
        public string $explicitBody,
        #[Header('X-Mode')]
        public string $mode = 'test',
        #[Ignore]
        #[Body]
        public ?string $ignoredBody = null,
    ) {}
}
