<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ConstructorOwned;

use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\DataTransfer\AbstractDto;

readonly class OutputDto extends AbstractDto
{
    #[To('kind')]
    public readonly string $value;

    public function __construct(public int $id, public array $_extra = [])
    {
        State::$calls++;
        $this->value = 'known';
    }
}
