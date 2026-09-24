<?php

declare(strict_types=1);

namespace ApiSutra\Extensions\Archive\Temp;

interface TempDirectoryProviderInterface
{
    public function createTempFile(?string $suffix = null): string;

    public function cleanup(string $path): void;
}
