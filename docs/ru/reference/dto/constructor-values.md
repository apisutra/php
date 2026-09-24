<!-- languages --> <a href="../../../en/reference/dto/constructor-values.md">English</a> · <a href="constructor-values.md">Русский</a> <!-- /languages -->
# Поля, установленные конструктором <a id="section-1"></a>

Для собственной модели используйте `#[ConstructorValue(allowMissing: false)]` на отдельном свойстве. Атрибут совместим с From/Map и defaults; сравнение и ограничения ниже одинаковы для обоих способов объявления.

`FieldRule::constructorValue(bool $allowMissing = false)` сравнивает входное значение
с уже установленным конструктором. Это позволяет проверять фиксированный `type`,
список разрешений или словарь настроек у plain DTO и наследников `AbstractDto`.
Readonly-свойство не записывается повторно. Конструктор вызывается один раз.

## Подключение <a id="section-2"></a>

```php
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use Example\ConstructorValues\RecordDto;

$rules = HydrationRules::create()->withDto(RecordDto::class, DtoRules::create()
    ->field('type', FieldRule::create()->constructorValue())
    ->field('permissions', FieldRule::create()->constructorValue(allowMissing: true))
    ->field('flags', FieldRule::create()->constructorValue(allowMissing: true)));
```

[Модель RecordDto](../../../example/constructor-values/src/RecordDto.php) устанавливает
`type = 'record'`, `permissions = ['read', 'write']` и словарь `flags` в конструкторе.
[Полный пример](../../../example/constructor-values/run.php) показывает успех, конфликт,
Missing и словарь с переставленными ключами:

```bash
php docs/example/constructor-values/run.php
```

Подключите `$rules` через `ClientConfig(hydration: new HydrationConfig(rules: $rules))` или
`Hydrator::forRules($rules)`. Все [входы гидратации](field-rules.md#section-5)
используют один контракт, в том числе cache hit и Ready await.

## Жизненный цикл и состояния <a id="section-3"></a>

Сначала выполняется обычная обработка входного поля: from/fallback, required и
forbidExplicitNull, defaults, форма, cast и проверка native-типа. Затем конструктор
создаёт значения отмеченных свойств; они проверяются до заполнения остальных полей.
`constructorValue()` совместим с группами FieldRule, но не может быть указан дважды.

| Состояние после обычной обработки | Результат |
| --- | --- |
| Поле присутствует | Сравнение с результатом конструктора |
| Missing, `allowMissing: false` | `required_field_missing` до конструктора |
| Missing, `allowMissing: true` | Сравнение пропущено; конструктор обязан установить допустимое значение |
| Missing с default/provider | Сравнение результата default/provider |
| Null | Обычная проверка nullable и затем сравнение с null |

`required()` проверяет исходное присутствие и не обходится через allowMissing/default.
`forbidExplicitNull()` срабатывает раньше default для Null. Обычная нормализация пустых
строк сохраняется; явный cast получает исходное значение согласно порядку преобразований.

Сравнение использует **итог того же Hydrator для обычного записываемого поля**.
В Legacy это включает преобразования пакета и финальную типизацию PHP. Например,
`float|string ← 5` обычно даёт строку `"5"`, а с `noTransform()` — float `5.0`.
В Strict остаются только [разрешённые преобразования](scalars.md).
Проверка не делает дополнительных приведений ради совпадения.

## Равенство <a id="section-4"></a>

- Скаляры и null сравниваются строго; `1`, `"1"` и `1.0` различаются после обработки.
  `NAN` не равен даже самому себе.
- Enum совпадает только с тем же case того же класса. Backed enum преобразуется
  штатно; для готового unit enum используйте `noTransform()` или явный cast.
- В списке важны длина, индексы, порядок значений и их типы.
- В словаре важны ключи и значения, порядок добавления ключей не важен.
- Массивы сравниваются рекурсивно, без сортировки, сериализации и преобразования листьев.
  Для преобразования элементов сначала задайте [shape или itemCast](shapes.md).

Сравниваются имеющиеся **PHP-ключи**. Массив `[1 => 'b', 0 => 'a']` равен `['a', 'b']`:
связи индексов со значениями одинаковы. `array_is_list()` не вводит отдельного отказа.
После JSON-декодирования ключ `"1"` уже стал int, `"01"` остаётся строкой;
пустые `{}` и `[]` при assoc-декодировании неразличимы. Проверка не восстанавливает
утраченные сведения о JSON.

## Границы и ошибки <a id="section-5"></a>

Нужно отдельное public stored property без hooks и default в декларации свойства,
доступный public конструктор (можно унаследованный). Имя свойства не должно совпадать
с параметром конструктора, включая promoted. Поддержаны scalar, null, конкретные enum,
array и nullable/union этих типов. Mixed, нетипизированные поля, arbitrary object,
интерфейсы, intersection, receiver и DTO/variants shape на любой глубине отклоняются
при компиляции набора. [Конфликты атрибутов и профилей](field-rules.md) сохраняются.

Обе стороны могут содержать только scalar/null/enum и их рекурсивные массивы.
До сравнения проверяется всё содержимое, даже за первым несовпадением.
Вход с объектом или ресурсом после преобразований даёт `invalid_field_type` до конструктора.
До 512 вложенных array-контейнеров включительно допустимо, включая последний пустой;
более глубокий или циклический вход даёт `hydration_depth_exceeded`. Повтор конечной
ветви разрешён. Неподдерживаемый/слишком глубокий результат конструктора или
неинициализированное свойство — `ConfigurationException` после одного вызова.

Допустимые, но разные значения дают `constructor_value_mismatch`. Новые проверки
указывают на **свойство целиком**, без внутренних ключей массива или значений.
SourcePath сохраняет выбранный fallback; после cast/provider показывает Boundary.
Ошибки прежних преобразований сохраняют их подробные пути. См. [диагностику](diagnostics.md).

Обычные поля не проверяются повторно; без opt-in fallback запрещён
записи в инициализированное свойство. Сериализатор не запускает сравнение и не исключает
поле из запроса: To и остальные исходящие правила продолжают работать.
