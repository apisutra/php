<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use JsonSerializable;

final readonly class OpaqueEnvelope implements JsonSerializable
{
    public function __construct(private object $dto)
    {
    }
    public function jsonSerialize(): mixed
    {
        return ['opaque' => $this->dto];
    }
}
