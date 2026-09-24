<?php

declare(strict_types=1);

namespace Example\Records;

use ApiSutra\Core\AbstractClient;
use ApiSutra\Contracts\Interfaces\Resolver\RequestNamespaceProviderInterface;
use Example\Records\Resources\Records\RecordsResource;
use Override;

final class DemoClient extends AbstractClient implements RequestNamespaceProviderInterface
{
    public const string REQUEST_NAMESPACE = __NAMESPACE__ . '\\Resources';

    /** @return list<string> */
    #[Override]
    public function requestNamespaces(): array
    {
        return [self::REQUEST_NAMESPACE];
    }

    public function records(): RecordsResource
    {
        return new RecordsResource($this);
    }
}
