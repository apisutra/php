<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

final class ArrayValue
{
    public function __construct(
        private readonly string $value,
    ) {}

    /**
     * Массив для сериализации.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['value' => $this->value];
    }
}
