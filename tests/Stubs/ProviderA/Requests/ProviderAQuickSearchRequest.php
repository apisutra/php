<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderA\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\ProviderA\Dto\ProviderASyncResponseDto;

#[Get('/provider-a/quick-search')]
#[Returns(ProviderASyncResponseDto::class)]
final class ProviderAQuickSearchRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $query,
    ) {}
}
