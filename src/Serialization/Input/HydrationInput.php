<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Input;

use ApiSutra\Support\ArrayPath;
use ApiSutra\Serialization\Rules\ContainerShape;

/** @internal Значение и форма выбранного исходного узла; пользователь получает только value. */
final readonly class HydrationInput
{
    public function __construct(
        public mixed $value,
        public ?SourceShapeMap $shape = null,
        public bool $jsonSourceKnown = false,
    ) {
    }

    public function kind(): ?ContainerShape
    {
        return SourceShapeMap::kindOf($this->value, $this->shape, $this->jsonSourceKnown);
    }

    public function select(string $path): self
    {
        return new self(ArrayPath::getByPath($this->value, $path), $this->shape?->select(explode('.', $path)), $this->jsonSourceKnown);
    }
}
