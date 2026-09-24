<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Casting;

use ApiSutra\Serialization\Context\SerializationContext;

interface SerializationCastInterface
{
    public function serialize(mixed $value, SerializationContext $context): mixed;
}
