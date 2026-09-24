<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingMetadata;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Tests\Stubs\MetadataIsolation\CreatedCast;
use ApiSutra\Tests\Stubs\MetadataIsolation\CreatedValue;

final readonly class SharedModel implements RequestInterface
{
    public function __construct(
        #[From('wire_number')]
        #[Query]
        #[Cast(CreatedCast::class, ['nested' => [new CreatedValue()]])]
        public int $number = 0,
    ) {
    }

    public function getMethod(): HttpMethod
    {
        return HttpMethod::GET;
    }

    public function getEndpoint(): string
    {
        return '/metadata';
    }

    public function getResponseType(): ?string
    {
        return null;
    }
}
