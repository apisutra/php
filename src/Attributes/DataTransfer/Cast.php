<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\DataTransfer;

use Attribute;
use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Contracts\Interfaces\Casting\SerializationCastInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Cast
{
    public array $args;

    /**
     * @param class-string<HydrationCastInterface|SerializationCastInterface> $class Класс каста.
     * @param mixed ...$args Аргументы конструктора каста.
     */
    public function __construct(
        public string $class,
        mixed ...$args,
    ) {
        $this->args = $args;
    }
}
