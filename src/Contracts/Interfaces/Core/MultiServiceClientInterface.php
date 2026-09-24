<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Core;

interface MultiServiceClientInterface
{
    /**
     * @return array<int, ClientInterface>
     */
    public function services(): array;
}
