<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\Header;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Http\QueryArrayFormat;

#[Post('/serialize/{id}')]
final class SerializationRequest extends AbstractRequest
{
    /**
     * @param array<int, string> $filters
     */
    public function __construct(
        #[Path('id')]
        public string $id,
        #[Query('filters', arrayFormat: QueryArrayFormat::Comma)]
        public array $filters,
        #[Body(nested: 'payload.data')]
        public string $payload,
        #[Header('X-Custom')]
        public string $header,
        public string $plainValue,
    ) {}
}
