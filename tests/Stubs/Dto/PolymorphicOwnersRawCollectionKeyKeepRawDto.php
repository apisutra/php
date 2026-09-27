<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Collections\RawCollection;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Enums\DataTransfer\DiscriminatorMode;
use ApiSutra\Enums\DataTransfer\UnknownVariant;

final readonly class PolymorphicOwnersRawCollectionKeyKeepRawDto extends AbstractDto
{
    public function __construct(
        #[Nested(
            discriminatorMode: DiscriminatorMode::Key,
            map: [
                'person' => PolymorphicOwnerPersonDto::class,
                'organization' => PolymorphicOwnerOrganizationDto::class,
            ],
            unknownVariant: UnknownVariant::KeepRaw,
        )]
        public RawCollection $owners,
    ) {}
}
