<?php

declare(strict_types=1);

namespace Example\DtoShowcase;

use ApiSutra\DataTransfer\AbstractDto;

final readonly class VideoDto extends AbstractDto
{
    public function __construct(
        public string $url,
        public int $duration,
        public string $type = 'video',
    ) {
    }
}
