<?php

declare(strict_types=1);

namespace Example\ClientShowcase\Resources\Records;

use ApiSutra\Core\AbstractResource;
use Example\ClientShowcase\Resources\Records\Get\GetRecordRequest;

final class RecordsResource extends AbstractResource
{
    public function get(int $id): GetRecordRequest
    {
        return (new GetRecordRequest($id))->setClient($this->client);
    }
}
