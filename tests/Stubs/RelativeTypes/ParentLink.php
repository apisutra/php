<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\RelativeTypes;

readonly class ParentLink extends BaseRecord
{
    public function __construct(public ?parent $link = null, int $id = 7)
    {
        parent::__construct(id: $id);
    }
}
