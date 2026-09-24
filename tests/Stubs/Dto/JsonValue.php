<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use JsonSerializable;

final class JsonValue implements JsonSerializable
{
    public function __construct(
        private readonly string $value,
    ) {}

    #[\Override]
    public function jsonSerialize(): array
    {
        return ['value' => $this->value];
    }
}
