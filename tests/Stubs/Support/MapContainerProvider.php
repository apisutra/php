<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Support;

use ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use Override;

final class MapContainerProvider implements ContainerProviderInterface
{
    /** @var list<string> */
    public array $resolved = [];

    /** @param array<string, object> $services */
    public function __construct(private array $services = [])
    {
    }

    #[Override]
    public function bound(string $id): bool { return isset($this->services[$id]); }
    #[Override]
    public function make(string $id): ?object
    {
        $this->resolved[] = $id;
        return $this->services[$id] ?? null;
    }
    #[Override]
    public function basePath(): ?string { return null; }
    #[Override]
    public function environment(): ?string { return null; }
    #[Override]
    public function isDebug(): ?bool { return null; }
    #[Override]
    public function validatorFactory(): ?object { return null; }
}
