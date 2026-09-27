<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\PolymorphicInput;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\InputShape;
use ApiSutra\Attributes\DataTransfer\RequiredInput;
use ApiSutra\Attributes\DataTransfer\ForbidExplicitNull;
use ApiSutra\Serialization\Rules\ContainerShape;

final readonly class DictionaryDto
{
    /** @param array<array-key, mixed> $participants */
    public function __construct(
        #[From('data.members')]
        #[InputShape(ContainerShape::Object)]
        #[RequiredInput]
        #[ForbidExplicitNull]
        #[Cast(DictionaryCast::class)]
        public array $participants,
    ) {
    }
}
