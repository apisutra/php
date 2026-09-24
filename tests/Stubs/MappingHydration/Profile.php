<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingHydration;

use ApiSutra\Config\DtoHydrationPolicy;
use ApiSutra\Contracts\Interfaces\Serialization\DtoHydrationProfileInterface;
use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Enums\DataTransfer\EmptyStringBehavior;

final readonly class Profile implements DtoHydrationProfileInterface
{
    public function __construct()
    {
        Trace::$events[] = 'profile-new';
    }

    public function policy(): DtoHydrationPolicy
    {
        Trace::$events[] = 'policy';
        return new DtoHydrationPolicy(
            namingStrategy: Trace::$snake ? NamingStrategy::SnakeCase : NamingStrategy::None,
            emptyStringBehavior: Trace::$snake ? EmptyStringBehavior::NullIfBlank : EmptyStringBehavior::Keep,
        );
    }

    public function casts(): array
    {
        Trace::$events[] = 'casts';
        return [];
    }
}
