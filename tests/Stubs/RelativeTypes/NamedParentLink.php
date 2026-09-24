<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\RelativeTypes;

final readonly class NamedParentLink extends BaseRecord
{
    public function __construct(public ?BaseRecord $link = null, int $id = 7)
    {
        parent::__construct(id: $id);
    }
}
