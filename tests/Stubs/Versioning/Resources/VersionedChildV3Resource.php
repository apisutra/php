<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Versioning\Resources;

use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Core\AbstractResource;

final class VersionedChildV3Resource extends AbstractResource
{
    public function __construct(
        ClientInterface $client,
        public string $token = 'v3',
    ) {
        parent::__construct($client);
    }
}
