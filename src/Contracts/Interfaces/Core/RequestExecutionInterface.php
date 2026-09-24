<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Core;

use ApiSutra\Request\PaginationOptions;

interface RequestExecutionInterface extends RequestInterface, RequestOptionsProviderInterface
{
    public function getRequest(): RequestInterface;

    public function getPaginationOptions(): PaginationOptions;
}
