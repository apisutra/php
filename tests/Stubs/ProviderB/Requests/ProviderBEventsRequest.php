<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderB\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\ProviderB\Dto\ProviderBEventsResponseDto;

#[Get('/provider-b/events/{operationId}')]
#[Returns(ProviderBEventsResponseDto::class)]
final class ProviderBEventsRequest extends AbstractRequest
{
    public function __construct(
        #[Path('operationId')]
        public string $operationId,
    ) {}
}
