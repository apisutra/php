<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records\Get\Dto;

use ApiSutra\Attributes\DataTransfer\DtoVariants;
use ApiSutra\DataTransfer\AbstractDto;

// Одна карта для корня JSON, одиночной обложки и каждого элемента списка assets.
#[DtoVariants(
    discriminator: 'type',
    map: ['image' => ImageAttachmentDto::class, 'document' => DocumentAttachmentDto::class],
    unknown: RawAttachmentDto::class,
)]
abstract readonly class AttachmentDto extends AbstractDto
{
}
