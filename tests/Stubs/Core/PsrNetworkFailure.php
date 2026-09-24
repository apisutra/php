<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Core;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

final class PsrNetworkFailure extends RuntimeException implements NetworkExceptionInterface
{
    public function __construct(private readonly RequestInterface $request)
    {
        parent::__construct('fixture network failure');
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
