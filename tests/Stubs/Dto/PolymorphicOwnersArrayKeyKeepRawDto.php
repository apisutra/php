<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Enums\DataTransfer\DiscriminatorMode;
use ApiSutra\Enums\DataTransfer\UnknownVariant;

final readonly class PolymorphicOwnersArrayKeyKeepRawDto extends AbstractDto
{
    /**
     * @param array<int, mixed> $owners
     */
    public function __construct(
        #[Nested(
            discriminatorMode: DiscriminatorMode::Key,
            map: [
                'person' => PolymorphicOwnerPersonDto::class,
                'organization' => PolymorphicOwnerOrganizationDto::class,
            ],
            unknownVariant: UnknownVariant::KeepRaw,
        )]
        public array $owners = [],
    ) {}
}
