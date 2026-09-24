<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Collections\RawCollection;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Enums\DataTransfer\NestedDiscriminatorMode;
use ApiSutra\Enums\DataTransfer\NestedUnknownVariant;

final readonly class PolymorphicOwnersRawCollectionKeyKeepRawDto extends AbstractDto
{
    public function __construct(
        #[Nested(
            discriminatorMode: NestedDiscriminatorMode::Key,
            map: [
                'person' => PolymorphicOwnerPersonDto::class,
                'organization' => PolymorphicOwnerOrganizationDto::class,
            ],
            unknownVariant: NestedUnknownVariant::KeepRaw,
        )]
        public RawCollection $owners,
    ) {}
}
