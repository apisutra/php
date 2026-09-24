<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Core;

use ApiSutra\Request\RequestOptions;

interface RequestOptionsProviderInterface
{
    public function getOptions(): RequestOptions;
}
