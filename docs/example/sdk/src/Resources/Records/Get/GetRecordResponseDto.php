<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records\Get;

use ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\DataTransfer\AbstractResponseDto;
use DateTimeImmutable;

// Типизированная модель записи, которую получит приложение.
final readonly class GetRecordResponseDto extends AbstractResponseDto
{
    /** @param array<string, mixed> $_extra */
    public function __construct(
        #[From('record_id', fallback: ['id'])] // Если record_id отсутствует, взять id.
        public int $id,
        public string $title,
        // Преобразовать строку created_at из ответа API в объект даты.
        #[From('created_at')]
        #[DateTimeFrom(format: DATE_ATOM)]
        public DateTimeImmutable $createdAt,
        #[From('author.name')] // Прочитать имя из вложенного объекта author.
        public ?string $authorName = null,
        #[EmptyStringAsNull(blank: true)] // Пустую строку и пробелы превратить в null.
        public ?string $description = null,
        // Неизвестные поля ответа; сбор включает Extras в правилах клиента.
        #[Extras]
        public array $_extra = [],
    ) {
    }
}
