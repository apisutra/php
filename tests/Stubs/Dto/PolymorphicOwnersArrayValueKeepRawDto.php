<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Enums\DataTransfer\NestedDiscriminatorMode;
use ApiSutra\Enums\DataTransfer\NestedUnknownVariant;

final readonly class PolymorphicOwnersArrayValueKeepRawDto extends AbstractDto
{
    /**
     * @param array<int, mixed> $owners
     */
    public function __construct(
        #[Nested(
            discriminator: 'kind',
            discriminatorMode: NestedDiscriminatorMode::Value,
            map: [
                'person' => PolymorphicOwnerPersonDto::class,
                'organization' => PolymorphicOwnerOrganizationDto::class,
            ],
            unknownVariant: NestedUnknownVariant::KeepRaw,
        )]
        public array $owners = [],
    ) {}
}
