<!-- languages --> <a href="../../../en/guides/dto/plain-models.md">English</a> · <a href="plain-models.md">Русский</a> <!-- /languages -->
# DTO без атрибутов <a id="section-1"></a>

Чтобы использовать модели без атрибутов ApiSutra, соберите неизменяемый `HydrationRules`
и передайте его как `ClientConfig(hydration: new HydrationConfig(rules: $rules), ...)`. Один набор описывает mapping, вложенные
DTO, строгие типы, присутствие полей и сохранение дополнительных данных. Для обработки
без клиента используйте `Hydrator::forRules($rules)`: Laravel, контейнер и HTTP не нужны.

## Сквозной пример <a id="section-2"></a>

[Опубликованный пример](../../examples/hydration-rules.md) состоит из отдельных
файлов DTO, набора правил и scoped cast. Запуск из checkout после Composer install:

```bash
php docs/example/hydration-rules/run.php
```

В установленном пакете путь начинается с `vendor/apisutra/php/`.
[run.php](../../../example/hydration-rules/run.php) строит один набор для standalone
и `ClientConfig`. Он описывает owner, список `rows` с проекцией `each: 'value'`,
строгий список ids, запрет явного null для count и receiver `_extra`.

Ожидается: owner.id = 7, owner._extra = `['future' => false]`, items[0].id = 8,
count = null. В корневом `_extra` сохраняются `next_feature: null` и соседние meta
элементов rows. [Форма остатка](../../reference/dto/extras.md) описана отдельно.

Передайте `$config` своему SDK-клиенту. `#[Returns(ReportDto::class)]` использует
тот же набор. Конструкторы `Hydrator`, `DtoSerializer` и `Serializer` принимают
необязательный `config: new HydrationConfig(rules: $rules)`. Клиент передаёт набор обоим направлениям.
`$config->with(hydration: null)` создаёт конфигурацию без набора;
`with()` без override сохраняет исходный набор.

`Hydrator::default()` и `DTO::from()` набор клиента не наследуют. Для одинакового
поведения standalone и клиента передавайте один набор явно. В Laravel собирайте
набор в provider/factory, а не сохраняйте живые descriptors в кешируемом config.

## Применение правил в SDK <a id="section-3"></a>

Рабочие атрибутные рецепты остаются доступны: [provider для Present/Null](../../reference/dto/defaults.md#section-9)
проверяет запрет null и форму до Nested; `Nested(itemCast:)` может проверить scalar-элемент
или вернуть raw-объект через фабрику. Такой itemCast создаётся без аргументов, поэтому
параметризованный строгий scalar cast требует отдельного класса. Bare array и PHPDoc
не дают проверки элементов. `list(list(dto(...)))` заменяет RowCast для двумерного списка
без отдельного DTO ряда. Raw-фабрика, напрямую создающая объект, гидратор не вызывает.

Не сочетайте конфликтующие входные атрибуты с внешним FieldRule для одного поля.
В casts/providers используйте полученный контекст для вложенной гидрации. Raw-фабрика
создаёт объекты независимо от гидратора. Выбирайте strict с учётом реальных типов JSON.
Клиент с набором исключает receiver из исходящих данных; cast всего объекта с видимым
receiver запрещён. Для результатов continuation см.
[правила готовности](../../reference/execution/continuation-await.md#section-10).

Для моделей, где конструктор сам задаёт `type` или фиксированные массивы, используйте
[constructorValue](../../reference/dto/constructor-values.md): вход проверяется после
штатных преобразований, readonly-свойство не записывается повторно.

Существующую доменную фабрику можно подключить через [пользовательский гидратор DTO](../../reference/dto/hydrators.md), в том числе с private-конструкторами и зависимостями приложения.
