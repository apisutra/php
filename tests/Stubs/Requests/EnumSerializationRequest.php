<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\Header;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Enums\TitleStatus;

#[Post('/enum/{status}')]
final class EnumSerializationRequest extends AbstractRequest
{
    /**
     * @param array<int, TitleStatus> $queryStatuses
     * @param array<int, TitleStatus> $bodyStatuses
     */
    public function __construct(
        #[Path('status')]
        public TitleStatus $pathStatus,
        #[Query('status_query')]
        public TitleStatus $queryStatus,
        #[Query('status_list')]
        public array $queryStatuses,
        #[Header('X-Status')]
        public TitleStatus $headerStatus,
        #[Body]
        public TitleStatus $bodyStatus,
        #[Body]
        public array $bodyStatuses,
    ) {}
}
