<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingHydration;

use ApiSutra\Attributes\DataTransfer\Cast as CastAttribute;
use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Attributes\DataTransfer\DtoHydrationProfile;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\DataTransfer\AbstractResponseDto;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\VO\Pipeline\PipelineContext;

#[DtoHydrationProfile(Profile::class)]
final readonly class ProfiledDto extends AbstractResponseDto
{
    public function __construct(
        #[From('payload.value', fallback: ['backup.value'])]
        #[DefaultValue(provider: Provider::class, when: [ValueState::Missing, ValueState::Null, ValueState::Present])]
        public int $count = 3,
        #[CastAttribute(Cast::class, new Argument())]
        public ?int $casted = null,
        public ?string $displayName = null,
        public ?Marker $marker = new Marker(),
    ) {
        Trace::$events[] = 'dto';
    }

    public static function computed(array $data, ?PipelineContext $context = null): array
    {
        Trace::$events[] = 'computed';
        return $data;
    }
}
