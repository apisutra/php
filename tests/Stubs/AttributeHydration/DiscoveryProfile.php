<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Config\DtoHydrationPolicy;
use ApiSutra\Contracts\Interfaces\Serialization\DtoHydrationProfileInterface;
use LogicException;

final readonly class DiscoveryProfile implements DtoHydrationProfileInterface
{
    public function __construct()
    {
        throw new LogicException('Поиск метаданных не должен создавать пользовательский профиль');
    }

    public function policy(): DtoHydrationPolicy
    {
        return new DtoHydrationPolicy();
    }

    public function casts(): array
    {
        return [];
    }
}
