<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Resources;

use ApiSutra\Core\AbstractResource;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;

final class TestResource extends AbstractResource
{
    public function makeRequest(string $query): SimpleGetRequest
    {
        return $this->request(SimpleGetRequest::class, $query);
    }
}
