<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Enums\DataTransfer\ValueState;

final readonly class ScopedAttributeDto
{
    public function __construct(
        #[Cast(ScopedChildCast::class)] public RecordDto $child,
        #[Nested(itemCast: ScopedRowCast::class)] public array $rows,
        #[DefaultValue(provider: ScopedChildProvider::class, when: [ValueState::Present])] public RecordDto $provided,
    ) {
    }
}
