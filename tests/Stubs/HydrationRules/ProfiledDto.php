<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

final readonly class ProfiledDto extends ProfiledBaseDto
{
    public function __construct(public RecordDto $child, public int $count)
    {
    }
}
