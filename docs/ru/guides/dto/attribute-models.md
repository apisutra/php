<!-- languages --> <a href="../../../en/guides/dto/attribute-models.md">English</a> · <a href="attribute-models.md">Русский</a> <!-- /languages -->
# DTO с атрибутами <a id="section-1"></a>

Атрибуты подходят модели, которая принадлежит SDK и хранит mapping рядом со свойством.
[RecordDto](../../../example/sdk/src/AttributeExample/RecordDto.php) — отдельный класс
с `From('record_id')`, типизированными id/title и базой `AbstractResponseDto`.

## Описать модель <a id="section-2"></a>

1. Выберите базу или реализуйте поддержанный [контракт DTO](../../reference/dto/models.md).
2. Используйте From/Map для пути входных данных; outgoing To задаётся отдельно.
3. Для одиночного вложенного объекта или списка примените
   [Nested](../../reference/dto/shapes.md). Native `array` не проверяет тип элементов.
4. Определите missing/null и defaults по [контракту присутствия](../../reference/dto/defaults.md).
5. Если нужен общий способ преобразования, подключите
   [профиль](../../reference/dto/profiles.md) или точечный Cast.

## Проверить <a id="section-3"></a>

Загрузите успешный массив, missing, null и неправильный тип. Убедитесь, что путь
ошибки указывает нужное свойство/элемент. Для outgoing отдельно проверьте
[DX/wire](../../reference/serialization/dto-output.md): входной mapping не определяет
автоматически формат запроса.

При переходе на [внешний набор](plain-models.md) сначала устраните пересечения
деклараций по [таблице конфликтов](../../reference/dto/field-rules.md).

[Сигнатуры атрибутов](../../reference/attributes/hydration.md) ·
[Маршрут DTO](../../start/describe-dto.md).
