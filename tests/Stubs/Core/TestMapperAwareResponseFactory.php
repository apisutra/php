<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Core;

use ApiSutra\Response\ClientResponse;
use ApiSutra\Response\ClientResponseFactoryInterface;
use ApiSutra\Result\ResolvedResultInterface;
use ApiSutra\VO\Errors\ClientErrorMapperAwareInterface;
use ApiSutra\VO\Errors\ClientErrorMapperInterface;
use ApiSutra\VO\Errors\RequestError;

final readonly class TestMapperAwareResponseFactory implements ClientResponseFactoryInterface, ClientErrorMapperAwareInterface
{
    public function __construct(
        private ClientErrorMapperInterface $mapper,
    ) {}

    public function withErrorMapper(ClientErrorMapperInterface $mapper): static
    {
        return new self($mapper);
    }

    public function make(ResolvedResultInterface $result): ClientResponse
    {
        $errors = $result->result()->errors;
        $mapped = array_map(
            fn (RequestError $error): array => $this->mapper->map($error)->toArray(),
            $errors->all(),
        );

        return new ClientResponse(
            status: $this->mapper->status($errors),
            body: ['errors' => $mapped],
        );
    }
}
