<?php

declare(strict_types=1);

namespace Example\ClientShowcase;

use ApiSutra\Core\AbstractClient;
use Example\ClientShowcase\Resources\Records\RecordsResource;

final class DemoClient extends AbstractClient
{
    public function records(): RecordsResource
    {
        return new RecordsResource($this);
    }
}
