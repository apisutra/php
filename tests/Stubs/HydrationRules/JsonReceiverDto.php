<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use JsonSerializable;

final readonly class JsonReceiverDto implements JsonSerializable
{
    public function __construct(public array $extra = [])
    {
    }
    public function jsonSerialize(): mixed
    {
        return ['custom' => $this->extra];
    }
}
