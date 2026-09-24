<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\DataTransfer;

use Attribute;

/**
 * Описывает бизнес-смысл DTO-поля для документации, анализа и export tooling.
 *
 * Атрибут не описывает техническую схему поля. Тип, nullable, enum, nested DTO,
 * collection shape, external field name и casts должны извлекаться автоматически
 * из PHP-типа и других data-transfer атрибутов (`From`, `Map`, `Nested`, `Cast` и т.п.).
 *
 * Общее правило заполнения:
 * optional-поля нельзя заполнять догадками. Значения должны опираться на явный
 * контракт провайдера, документацию, пользовательское описание или проверенный анализ.
 * Если данных недостаточно, поле остаётся null.
 *
 * Это особенно важно для AI-assisted разметки DTO: ассистент должен фиксировать
 * только известные факты и не достраивать бизнес-смысл самостоятельно.
 *
 * Поля:
 * - title: обязательное короткое человекочитаемое имя поля. Используется в таблицах,
 *   карточках DTO и summary-представлениях.
 * - description: развёрнутое бизнес-описание поля: что означает значение, откуда оно
 *   берётся, как его интерпретировать в предметной области.
 * - example: один типовой пример значения. Может быть scalar, JSON-строка или array.
 *   Array удобно использовать для структурированных object/list примеров, потому что
 *   exporter может отрендерить его как JSON. String остаётся буквальным примером
 *   значения и не должна автоматически парситься как JSON.
 * - examples: несколько примеров. Каждый элемент подчиняется тем же правилам, что
 *   и `example`.
 * - format: человекочитаемая подсказка о формате значения, если PHP-типа недостаточно
 *   (`ДД.ММ.ГГГГ`, `12 цифр`, `ISO 8601`, `money`, `percent`, `enum code`).
 * - nullableReason: объяснение, почему поле может быть null. Заполняется только если
 *   причина явно известна: провайдер описал условие отсутствия, пользователь это сообщил,
 *   или это достоверно следует из контракта. Если причина неизвестна или есть только
 *   предположение, поле нужно оставить null.
 * - note: дополнительная оговорка, ограничение или важный нюанс, который не относится
 *   напрямую к основному описанию.
 *
 * Минимальный рекомендуемый вариант — заполнить только title. Остальные поля заполняются
 * тогда, когда они реально улучшают понимание response DTO или качество генерируемой
 * документации.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class About
{
    /**
     * @param string|int|float|bool|array<array-key, string|int|float|bool|array|null>|null $example
     * @param array<int, string|int|float|bool|array<array-key, string|int|float|bool|array|null>|null>|null $examples
     */
    public function __construct(
        public string $title,
        public ?string $description = null,
        public string|int|float|bool|array|null $example = null,
        public ?array $examples = null,
        public ?string $format = null,
        public ?string $nullableReason = null,
        public ?string $note = null,
    ) {
    }
}
