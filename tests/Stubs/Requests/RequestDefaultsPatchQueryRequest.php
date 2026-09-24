<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Patch;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\File;
use ApiSutra\Attributes\Request\Header;
use ApiSutra\Attributes\Request\Ignore;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Request\RequestDefaults;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Http\QueryArrayFormat;
use ApiSutra\Enums\Request\RequestUnmappedTarget;
use ApiSutra\VO\Files\FileInput;

#[Patch('/defaults/{id}')]
#[RequestDefaults(unmapped: RequestUnmappedTarget::Query)]
final class RequestDefaultsPatchQueryRequest extends AbstractRequest
{
    /**
     * @param array<int, string> $tags
     */
    public function __construct(
        #[Path('id')]
        public string $id,
        public string $plain,
        #[Body('payload.forced')]
        public string $forcedBody,
        #[Query('tags', arrayFormat: QueryArrayFormat::Comma)]
        public array $tags,
        #[Header('X-Mode')]
        public string $mode = 'test',
        #[File('document')]
        public ?FileInput $document = null,
        #[Ignore]
        public ?string $ignored = null,
    ) {}
}
