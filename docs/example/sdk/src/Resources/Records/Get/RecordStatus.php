<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records\Get;

enum RecordStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function title(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Published => 'Опубликована',
            self::Archived => 'В архиве',
        };
    }
}
