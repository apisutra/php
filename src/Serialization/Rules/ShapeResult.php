<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

/** @internal Преобразованное значение и фактически потреблённая часть его источника. */
final readonly class ShapeResult
{
    public function __construct(public mixed $value, public SourceConsumption $consumed, public bool $skip = false)
    {
    }
}
