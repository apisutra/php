<?php

declare(strict_types=1);

namespace Example\ResultErrors;

use ApiSutra\Core\AbstractClient;

final class DemoClient extends AbstractClient
{
    public function account(): AccountInfo
    {
        return $this->send(new GetAccountRequest())->dataOrFail();
    }

    public function owner(): AccountInfo
    {
        return $this->send(new GetOwnerRequest())->dataOrFail();
    }
}
