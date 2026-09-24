<?php

declare(strict_types=1);

namespace ApiSutra\Support;

use ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;

/**
 * Заглушка провайдера контейнера.
 */
final readonly class NullContainerProvider implements ContainerProviderInterface
{
    #[\Override]
    public function bound(string $id): bool
    {
        return false;
    }

    #[\Override]
    public function make(string $id): ?object
    {
        return null;
    }

    #[\Override]
    public function basePath(): ?string
    {
        return null;
    }

    #[\Override]
    public function environment(): ?string
    {
        return null;
    }

    #[\Override]
    public function isDebug(): ?bool
    {
        return null;
    }

    #[\Override]
    public function validatorFactory(): ?object
    {
        return null;
    }
}
