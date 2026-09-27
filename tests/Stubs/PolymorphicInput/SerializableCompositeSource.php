<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\PolymorphicInput;

use JsonSerializable;

final readonly class SerializableCompositeSource implements JsonSerializable
{
    /** @param array<array-key, mixed> $data */
    public function __construct(private array $data)
    {
    }

    /** @return array<array-key, mixed> */
    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
