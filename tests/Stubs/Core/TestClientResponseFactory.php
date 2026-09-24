<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Core;

use ApiSutra\Response\ClientResponse;
use ApiSutra\Response\ClientResponseFactoryInterface;
use ApiSutra\Result\ResolvedResultInterface;

final readonly class TestClientResponseFactory implements ClientResponseFactoryInterface
{
    public function make(ResolvedResultInterface $result): ClientResponse
    {
        return new ClientResponse(
            status: 201,
            headers: ['X-Test' => 'ok'],
            body: ['ok' => true],
        );
    }
}
