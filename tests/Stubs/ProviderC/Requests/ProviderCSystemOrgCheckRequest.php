<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderC\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\ProviderC\Dto\ProviderCSystemResponseDto;

#[Get('/org-check.json')]
#[Returns(ProviderCSystemResponseDto::class)]
final class ProviderCSystemOrgCheckRequest extends AbstractRequest
{
    public function __construct(
        #[Query(name: 'token')]
        public string $token,
        #[Query(name: 'inn')]
        public ?string $inn = null,
        #[Query(name: 'ogrn')]
        public ?string $ogrn = null,
    ) {}
}
