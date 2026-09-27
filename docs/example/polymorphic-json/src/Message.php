<?php

declare(strict_types=1);

namespace Example\PolymorphicJson;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\InputShape;
use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Rules\ContainerShape;
use ApiSutra\Serialization\Shapes\DtoShape;
use ApiSutra\Serialization\Shapes\ListShape;

final readonly class Message
{
    /**
     * @param array<array-key, string> $participants
     * @param list<Attachment> $attachments
     */
    public function __construct(
        public int $id,
        #[InputShape(ContainerShape::Object)]
        #[Cast(ParticipantsCast::class)]
        public array $participants,
        public Attachment $featured,
        #[Shape(new ListShape(new DtoShape(Attachment::class)))] public array $attachments,
    ) {
    }
}
