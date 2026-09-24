<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Auth;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Tests\Stubs\Dto\TokenResponseDto;
use ApiSutra\VO\Pipeline\PipelineContext;
use Override;

#[Post('/refresh')]
#[Returns(TokenResponseDto::class)]
final class ClientAwareRefreshRequest extends AbstractRequest
{
    public function __construct(private ?string $authScope = null)
    {
    }

    #[Override]
    public function getOptions(): RequestOptions
    {
        $options = parent::getOptions()->withTimeout(7);
        return $this->authScope === null ? $options : $options->withAuthScope($this->authScope);
    }

    #[Override]
    protected function currentOptions(): RequestOptions
    {
        return $this->getOptions();
    }

    #[Override]
    protected function beforeSend(PipelineContext $context): void
    {
        $context->preparedRequest = $context->preparedRequest
            ->withHeader('X-Client-Method', $this->getClient()->getConfig()->baseUrl)
            ->withHeader('X-Client-Property', $this->client->getConfig()->baseUrl);
    }
}
