<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderC\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\ProviderC\Dto\ProviderCSystemResponseDto;

#[Get('/people-check.json')]
#[Returns(ProviderCSystemResponseDto::class)]
final class ProviderCSystemPeopleCheckRequest extends AbstractRequest
{
    public function __construct(
        #[Query(name: 'token')]
        public string $token,
        #[Query(name: 'PeopleQuery.LastName')]
        public string $lastName,
        #[Query(name: 'PeopleQuery.FirstName')]
        public string $firstName,
        #[Query(name: 'regions')]
        public string $regions,
        #[Query(name: 'PeopleQuery.BirthDate')]
        public ?string $birthDate = null,
    ) {}
}
