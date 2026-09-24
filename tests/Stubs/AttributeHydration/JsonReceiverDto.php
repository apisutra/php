<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\Extras;
use JsonSerializable;

final readonly class JsonReceiverDto implements JsonSerializable
{
    public function __construct(#[Extras] public array $extra = [])
    {
    }
    public function jsonSerialize(): mixed
    {
        return ['custom' => $this->extra];
    }
}
