<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\Header;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Tests\Stubs\Dto\ContractFlowDto;
use Override;

#[Post('/items/{id}')]
#[Returns(ContractFlowDto::class)]
final class ContractFlowRequest extends AbstractRequest
{
    public function __construct(
        private HttpMethod $method,
        #[Path] public string $id = 'a/b',
        #[Query] public int $offset = 0,
        #[Header('X-Fixture')] public string $header = 'fixture',
        #[Body] public bool $enabled = false,
    ) {
    }

    #[Override]
    public function getMethod(): HttpMethod
    {
        return $this->method;
    }
}
