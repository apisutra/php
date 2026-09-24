<?php

declare(strict_types=1);

namespace Example\DtoShowcase;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use ApiSutra\Attributes\DataTransfer\DateTimeTo;
use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Attributes\DataTransfer\DtoSerialize;
use ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\Map;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Attributes\DataTransfer\To;
use ApiSutra\Casts\DataUriBase64FileCast;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Enums\Serialization\EnumOutput;
use ApiSutra\VO\Files\Base64File;
use ApiSutra\Attributes\DataTransfer\ConstructorValue;
use ApiSutra\Attributes\DataTransfer\RequiredInput;
use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\Attributes\DataTransfer\ForbidExplicitNull;
use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Enums\DataTransfer\NestedUnknownVariant;
use ApiSutra\Serialization\Rules\ScalarType;
use ApiSutra\Serialization\Shapes\ListShape;
use ApiSutra\Serialization\Shapes\VariantsShape;
use DateTimeImmutable;

// toArray() сохраняет null и строковые значения enum.
#[DtoSerialize(enumOutput: EnumOutput::Value, serializeNulls: true)]
final readonly class CatalogItemDto extends AbstractDto
{
    // Значение конструктора проверяется по входу без повторной записи в readonly.
    #[ConstructorValue]
    public string $kind;

    /**
     * @param list<int> $relatedIds
     * @param list<ImageDto|VideoDto> $media
     * @param array<string, mixed> $_extra
     */
    public function __construct(
        // Входное имя с запасным путём; исходящее имя задаётся отдельно.
        #[From('product_id', fallback: ['id'])]
        #[To('product_id')]
        #[RequiredInput]
        public int $id,
        // Одно внешнее имя для чтения и записи.
        #[Map('vendor_code')]
        public string $sku,
        // Отсутствие и явный null разрешены контрактом вымышленного API.
        #[DefaultValue('Без названия', when: [ValueState::Missing, ValueState::Null])]
        public string $title,
        // Ключ обязателен, но его значение может быть null или пустой строкой.
        #[EmptyStringAsNull(blank: true)]
        public ?string $description,
        // Общая Strict-policy проверяет точный тип bool.
        public bool $available,
        // Вложенный путь можно развернуть в отдельное свойство DTO.
        #[From('metrics.rating')]
        public float $rating,
        // Вход содержит время с часовым поясом, исходящий формат — календарную дату.
        #[From('created_at')]
        #[To('created_at')]
        #[DateTimeFrom(format: DATE_ATOM, strictFormat: true)]
        #[DateTimeTo(format: 'Y-m-d', timezone: 'UTC')]
        public DateTimeImmutable $createdAt,
        // Строковое значение API превращается в backed enum.
        #[From('state')]
        #[To('state')]
        public ItemStatus $status,
        // Собственный cast читает "12.34" как 1234 и выполняет обратное преобразование.
        #[From('price')]
        #[To('price')]
        #[Cast(MinorUnitsCast::class)]
        public int $priceMinor,
        // Файл внутри JSON: вход допускает data URI, выход содержит чистый Base64.
        #[Map('manual_file')]
        #[Cast(DataUriBase64FileCast::class)]
        public Base64File $manual,
        // SellerDto — обычный PHP-класс; Nested создаёт отдельный вложенный объект.
        #[Nested(type: SellerDto::class)]
        public SellerDto $seller,
        // Элементы становятся DTO, контейнер проверяет их тип и даёт first()/count().
        #[Nested(type: TagDto::class)]
        public TagCollection $tags,
        // Shape проверяет каждый элемент списка; PHPDoc нужен для IDE.
        #[From('related_ids')]
        #[To('related_ids')]
        #[RequiredInput]
        #[Shape(new ListShape(ScalarType::Int))]
        public array $relatedIds,
        // Каждый value становится DTO варианта; соседний rank остаётся в extras.
        #[From('assets')]
        #[To('assets')]
        #[RequiredInput]
        #[Shape(new ListShape(new VariantsShape('type', [
            'image' => ImageDto::class,
            'video' => VideoDto::class,
        ], unknown: NestedUnknownVariant::Error), each: 'value'))]
        public array $media,
        // Provider вычисляет отсутствующее значение по исходным данным DTO.
        #[DefaultValue(provider: DisplayNameProvider::class)]
        public string $displayName,
        // Отсутствие разрешено; исходный null запрещён.
        #[ForbidExplicitNull]
        public ?int $stock = null,
        // Непрочитанные поля сохраняются здесь и исключаются из запросов клиента.
        #[Extras]
        public array $_extra = [],
    ) {
        $this->kind = 'catalog_item';
    }
}
