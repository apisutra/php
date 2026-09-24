<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Validation;

use ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use Override;
use Throwable;

final class ScopedValidationProvider implements ContainerProviderInterface
{
    public int $factoryCalls = 0;

    public function __construct(private readonly ?object $factory) {}

    #[Override]
    public function bound(string $id): bool { return false; }
    #[Override]
    public function make(string $id): ?object { return null; }
    #[Override]
    public function basePath(): ?string { return null; }
    #[Override]
    public function environment(): ?string { return null; }
    #[Override]
    public function isDebug(): ?bool { return null; }
    #[Override]
    public function validatorFactory(): ?object
    {
        $this->factoryCalls++;
        if ($this->factory instanceof Throwable) {
            throw $this->factory;
        }
        return $this->factory;
    }
}
