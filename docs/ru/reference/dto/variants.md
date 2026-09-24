<!-- languages --> <a href="../../../en/reference/dto/variants.md">English</a> · <a href="variants.md">Русский</a> <!-- /languages -->
# Варианты элементов списка <a id="section-1"></a>

## Discriminator <a id="section-2"></a>

`ValueShape::variants(string $discriminator, array $map, NestedDiscriminatorMode $mode = Value,
NestedUnknownVariant $unknown = KeepRaw)` применяется только как элемент `list()`.
Enums расположены в `ApiSutra\Enums\DataTransfer`.

Value выбирает класс по значению пути discriminator; Key — по первому ключу обёртки
(пустой discriminator означает текущий объект). Map содержит значения/ключи и классы DTO.
Неизвестный или отсутствующий вариант обрабатывается через KeepRaw, Skip или Error.
Error даёт `unknown_nested_variant`. Ошибка известного варианта никогда не подавляется.
KeepRaw несовместим с typed collection, принимающей только DTO: это ошибка конфигурации.
