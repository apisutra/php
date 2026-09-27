<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Enums\DataTransfer\DiscriminatorMode;
use ApiSutra\Enums\DataTransfer\UnknownVariant;

final readonly class PolymorphicOwnersArrayValueKeepRawDto extends AbstractDto
{
    /**
     * @param array<int, mixed> $owners
     */
    public function __construct(
        #[Nested(
            discriminator: 'kind',
            discriminatorMode: DiscriminatorMode::Value,
            map: [
                'person' => PolymorphicOwnerPersonDto::class,
                'organization' => PolymorphicOwnerOrganizationDto::class,
            ],
            unknownVariant: UnknownVariant::KeepRaw,
        )]
        public array $owners = [],
    ) {}
}
