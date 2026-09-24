<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Core;

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\VO\Errors\ClientError;
use ApiSutra\VO\Errors\ClientErrorMapperInterface;
use ApiSutra\VO\Errors\RequestError;

final readonly class TestClientErrorMapper implements ClientErrorMapperInterface
{
    public function map(RequestError $error): ClientError
    {
        return new ClientError(
            providerCode: 'P-1',
            sdkCode: $error->code,
            clientCode: 'client.custom',
            appCode: 'APP-001',
            message: 'Кастомная ошибка',
            context: ['source' => 'test'],
            nested: [],
            requestClass: $error->requestClass,
        );
    }

    public function status(ErrorCollection $errors): int
    {
        return 418;
    }
}
