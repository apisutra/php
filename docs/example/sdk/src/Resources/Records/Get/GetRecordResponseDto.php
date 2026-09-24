<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records\Get;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\ConstructorValue;
use ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use ApiSutra\Attributes\DataTransfer\DateTimeTo;
use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Attributes\DataTransfer\DtoSerialize;
use ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\Attributes\DataTransfer\ForbidExplicitNull;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\Map;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Attributes\DataTransfer\RequiredInput;
use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Enums\DataTransfer\NestedUnknownVariant;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Enums\Serialization\EnumOutput;
use ApiSutra\Serialization\Rules\ScalarType;
use ApiSutra\Serialization\Shapes\ListShape;
use ApiSutra\Serialization\Shapes\VariantsShape;
use DateTimeImmutable;
use Example\Records\Resources\Records\Get\Dto\AuthorDto;
use Example\Records\Resources\Records\Get\Dto\DocumentAttachmentDto;
use Example\Records\Resources\Records\Get\Dto\ImageAttachmentDto;
use Example\Records\Resources\Records\Get\Dto\TagCollection;
use Example\Records\Resources\Records\Get\Dto\TagDto;

// toArray() сохраняет null, сериализует enum в значение и обходит вложенные объекты.
#[DtoSerialize(enumOutput: EnumOutput::Value, serializeNulls: true)]
final readonly class GetRecordResponseDto extends AbstractDto
{
    // Конструктор задаёт значение; гидратор проверяет совпадение с ответом провайдера.
    #[ConstructorValue]
    public string $kind;

    /**
     * @param list<ImageAttachmentDto|DocumentAttachmentDto> $attachments
     * @param list<int> $relatedIds
     * @param array<string, mixed> $_extra
     */
    public function __construct(
        // Запасной путь применяется при отсутствии record_id, но не при явном null.
        #[From('record_id', fallback: ['id'])]
        #[To('record_id')]
        #[RequiredInput]
        public int $id,
        #[DefaultValue('Без названия', when: [ValueState::Missing, ValueState::Null])]
        public string $title,
        // Сохраняем входной часовой пояс в объекте, а на выходе нормализуем время в UTC.
        #[From('created_at')]
        #[To('created_at')]
        #[DateTimeFrom(format: DATE_ATOM, strictFormat: true)]
        #[DateTimeTo(format: DATE_ATOM, timezone: 'UTC')]
        public DateTimeImmutable $createdAt,
        // Map задаёт одно внешнее имя в обе стороны; строка state становится enum.
        #[Map('state')]
        public RecordStatus $status,
        #[Nested(type: AuthorDto::class)]
        public AuthorDto $author,
        // Отсутствующая typed collection автоматически становится пустой коллекцией.
        #[Nested(type: TagDto::class)]
        public TagCollection $tags,
        // Выбираем тип по value.type. Неизвестный вариант — ошибка, а не потеря элемента.
        // Соседние поля обёртки (rank) сохранятся в _extra владельца списка.
        #[Map('assets')]
        #[RequiredInput]
        #[Shape(new ListShape(new VariantsShape('type', [
            'image' => ImageAttachmentDto::class,
            'document' => DocumentAttachmentDto::class,
        ], unknown: NestedUnknownVariant::Error), each: 'value'))]
        public array $attachments,
        // PHPDoc помогает IDE; Shape проверяет каждый элемент в рантайме.
        #[Map('related_ids')]
        #[RequiredInput]
        #[Shape(new ListShape(ScalarType::Int))]
        public array $relatedIds,
        #[From('metrics.rating')]
        public float $rating,
        // Собственный двусторонний cast: "03:05" из API ↔ 185 секунд в приложении.
        #[Map('reading_time')]
        #[Cast(ReadingTimeCast::class)]
        public int $readingTimeSeconds,
        // Большой идентификатор хранится строкой: никакого float и потери цифр.
        #[Map('external_id')]
        public string $externalId,
        // Strict-политика клиента не принимает "false" или 0 вместо boolean.
        public bool $featured,
        #[EmptyStringAsNull(blank: true)]
        public ?string $description = null,
        #[Map('updated_at')]
        #[DateTimeFrom(format: DATE_ATOM, strictFormat: true)]
        #[DateTimeTo(format: DATE_ATOM, timezone: 'UTC')]
        public ?DateTimeImmutable $updatedAt = null,
        // Отсутствие даёт default null; явный null провайдера запрещён.
        #[ForbidExplicitNull]
        public ?int $revision = null,
        // Остатки metrics/assets и неизвестные поля не теряются при toArray().
        #[Extras]
        public array $_extra = [],
    ) {
        $this->kind = 'record';
    }
}
