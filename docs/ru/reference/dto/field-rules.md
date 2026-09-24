<!-- languages --> <a href="../../../en/reference/dto/field-rules.md">English</a> · <a href="field-rules.md">Русский</a> <!-- /languages -->
# Внешние правила и конфликты деклараций <a id="section-1"></a>

## HydrationRules <a id="section-2"></a>

`ClientConfig::hydration` имеет тип `?HydrationConfig` и по умолчанию равен null.
Клиент передаёт набор своему гидратору и сериализатору. Он применяется к Returns,
пагинации, composite и await; объявленный receiver исключается из запросов клиента.
Копирование через `with()` сохраняет набор, явный null отключает его в новой копии.

[Практический пример](../../guides/dto/plain-models.md) показывает сборку набора.
`ClientConfig::casts` относится к исходящему преобразованию; его реестр гидратор не читает.

## Правила и проверка конфигурации <a id="section-3"></a>

Все публичные descriptors находятся в `ApiSutra\Serialization\Rules`.
Методы builders возвращают новый объект. Класс регистрируется точно по имени;
его схема не меняется в зависимости от пути в графе.

| API | Назначение |
| --- | --- |
| `HydrationRules::create(?RulePolicy $defaults = null)` | Общие defaults набора |
| `withDto(string $class, DtoRules $rules)` | Правила одного класса; повтор запрещён |
| `defaults()`, `rulesFor(string $class)` | Чтение defaults и правил класса |
| `DtoRules::create(?RulePolicy $policy = null)` | Policy класса |
| `field(string $property, FieldRule $rule)` | Одно правило физического свойства |
| `extras(string $property)` | Одно свойство для остатка источника |
| `FieldRule::create()->from(string $path, string ...$fallback)` | Primary и запасные пути через точку; null primary считается найденным |
| `shape(ValueShape $shape)` | Преобразование вложенной формы |
| `cast(HandlerSpec $cast, ?ValueShape $result = null)` | Готовое значение от cast; result только проверяет его |
| `noTransform()` | Явное отсутствие преобразования; native-тип всё равно проверяется |
| `constructorValue(bool $allowMissing = false)` | [Проверка значения конструктора без повторной записи](constructor-values.md) |
| `required()`, `forbidExplicitNull()` | Присутствие ключа и запрет исходного null |
| `inputShape(InputShape $shape)` | Проверка Object/List перед cast или noTransform |
| `default(DefaultSpec $default)`, `policy(RulePolicy $policy)` | Default и policy поля |

Повторные field/receiver, повтор группы from/default/policy или сочетание нескольких
преобразований (`shape`, `cast`, `noTransform`) дают `ConfigurationException`.
Гидратор компилирует набор при `forRules()` или создании клиента: проверяет классы,
свойства, receiver, ссылки и discriminator map. DTO-конструкторы при этом не вызываются.

`FieldRule` конфликтует с входными атрибутами **того же свойства**: From, Map, Nested,
Cast, DateTimeFrom, EmptyStringAsNull, DefaultValue, RequiredInput, ForbidExplicitNull,
ConstructorValue, Shape. Extras проверяется отдельно: нельзя одновременно объявить
атрибутный и внешний receiver одного класса. Атрибуты соседнего поля остаются
рабочими. To, DateTimeTo и посторонние атрибуты не конфликтуют. `DtoRules` конфликтует
с собственным или унаследованным DtoHydrate/DtoHydrationProfile.

## Приоритеты policy <a id="section-4"></a>

`RulePolicy` принимает nullable `scalars`, `emptyString`, `naming`, `dateTime`
и массив `casts` вида `имя PHP-типа => HandlerSpec`. Nullable означает «не задано».
Policy поля перекрывает policy класса, затем defaults набора, затем общую
HydrationConfig.policy и defaults ядра. [Полная таблица с профилями](configuration.md#section-2).
`dateTime` — целый `DateTimeHydrationPolicy`, без слияния отдельных полей объекта.

Приоритет преобразования: cast поля → casts класса → casts набора → встроенные
преобразования. `RulePolicy::casts` на уровне поля запрещён: используйте `cast()`.
`noTransform()` выключает этот выбор. Класс с профилем, не зарегистрированный через
`DtoRules`, полностью сохраняет правила профиля и игнорирует defaults набора.
При входе в дочерний DTO policy выбирается заново: Legacy родителя не отменяет strict ребёнка.

## Полный путь применения <a id="section-5"></a>

Набор подключается как `new HydrationConfig(rules: $rules)`.
Набор клиента действует при обычном Returns, promise API, пагинации, composite,
await и повторном awaitAs. HTTP-кеш хранит исходный ответ: DTO строится текущим набором.
Ненулевой результат response handler, RawResponse и Download обходят обычную гидратацию.

`Hydrator::forRules($rules)` явно подключает тот же набор standalone.
`Hydrator::default()` и `DTO::from()` набор клиента не наследуют. Создание набора
не меняет семантику объектов, которые уже существуют. Для рекурсии пользовательского
cast/provider используйте [scope](scope.md), а не default().

[Strict](scalars.md) · [Формы](shapes.md) · [Defaults](defaults.md) ·
[Extras](extras.md) · [Диагностика](diagnostics.md).
