<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Tracing;

use ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use ApiSutra\Contracts\Interfaces\Diagnostics\ExecutionObserverInterface;

final readonly class ObserverProvider implements ContainerProviderInterface
{
    public function __construct(private ExecutionObserverInterface $observer) {}
    public function bound(string $id): bool { return $id === ExecutionObserverInterface::class; }
    public function make(string $id): ?object { return $this->bound($id) ? $this->observer : null; }
    public function basePath(): ?string { return null; }
    public function environment(): ?string { return null; }
    public function isDebug(): ?bool { return null; }
    public function validatorFactory(): ?object { return null; }
}
