<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Enums;

enum BadTitleStatus: string
{
    case Active = 'active';

    /**
     * @return array<int, string>
     */
    public function title(): array
    {
        return ['bad'];
    }
}
