<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\TransferProgress;

use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Http\HttpMethod;
use Override;

class GateRequest extends AbstractRequest
{
    public function __construct(private string $endpoint)
    {
    }

    #[Override]
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    #[Override]
    public function getMethod(): HttpMethod
    {
        return HttpMethod::GET;
    }
}
