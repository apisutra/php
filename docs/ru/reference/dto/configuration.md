<!-- languages --> <a href="../../../en/reference/dto/configuration.md">English</a> · <a href="configuration.md">Русский</a> <!-- /languages -->
# Общая конфигурация гидратации <a id="section-1"></a>

`HydrationConfig` объединяет входную policy, необязательные внешние правила и пользовательский гидратор.
Собственные модели описывают поля [атрибутами](declarations.md), чужие —
[HydrationRules](field-rules.md). Обязательного списка дочерних DTO нет.

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;

$hydration = new HydrationConfig(policy: new RulePolicy(
    scalars: ScalarPolicy::Strict,
    naming: NamingStrategy::SnakeCase,
));
$config = new ClientConfig(baseUrl: 'https://api.example.test', hydration: $hydration);
$hydrator = Hydrator::forConfig($hydration);
```

Конструктор: `HydrationConfig(?RulePolicy $policy = null, ?HydrationRules $rules = null, ?DtoHydratorInterface $hydrator = null)`.
Объект неизменяемый; null policy оставляет defaults ядра, а не включает Strict.
Входной naming независим от `ClientConfig::namingStrategy`, который задаёт имена
исходящего запроса. Пустой блок отличается от отсутствующего при
[items-only пагинации](../execution/pagination.md): он явно включает обработку itemsType.

`$config->with()` сохраняет блок, `with(hydration: null)` удаляет его только из копии.
Атрибуты продолжают работать при входе в DTO; глобальный `Hydrator::default()` и
`DTO::from()` не получают конфигурацию других клиентов. Для самостоятельного
внешнего набора сохраняется `Hydrator::forRules($rules)`: это делегат forConfig.

## Приоритеты <a id="section-2"></a>

| Источник | Порядок от нижнего к верхнему |
| --- | --- |
| Без профиля модели | Defaults ядра → Config.policy → Rules.defaults → DtoRules.policy → FieldRule.policy |
| DtoHydrationProfile | Defaults ядра → Config.policy → полный policy профиля для naming/date/empty-string |
| DtoHydrate | Явно заданные компоненты ближайшего атрибута перекрывают применимую базу |
| Внешний набор и профиль | Rules.defaults исключаются; явный DtoRules того же класса конфликтует |
| ScalarPolicy | DtoHydrationProfile не объявляет scalars; общий Config Strict сохраняется, DtoHydrate.scalars может его заменить |

Nullable-компоненты RulePolicy и DtoHydrate означают «не задано». None/Keep и defaults
дат полного профиля являются настоящими значениями. RulePolicy.dateTime заменяет
компонент целиком; отдельные параметры DtoHydrate накладываются точечно.
Для поля From/Map/Nested.from, DateTimeFrom и EmptyStringAsNull сохраняют свои
приоритеты. Cast обходит нормализацию пустой строки.

Преобразование: Cast поля / внешний handler → профильный cast по типу → применимый
policy cast → builtin. Профильный cast не стирает общие handlers других типов.
Наличие cast не отменяет финальную Strict-проверку. Nested имеет приоритет над Cast и следует объявленному поведению объекта/списка.

Общий Config Strict + DtoHydrate только с naming остаётся Strict.
Rules.defaults Strict не применяется к модели с DtoHydrate; без другой scalar policy
такая модель использует Legacy. Для общей Strict-политики задайте Config.policy.

## Где действует блок <a id="section-3"></a>

Returns, async, вложенность, коллекции, composite, Ready и повторный awaitAs
используют один гидратор клиента. HTTP-кеш хранит ответ, а DTO строится текущей
конфигурацией. Cast/provider получают текущие правила через [контекст](scope.md).
Ненулевой response handler, RawResponse и Download сохраняют обход гидратации.

Клиентский сериализатор использует то же описание receiver. `Serializer` всегда
строит wire-представление; `DtoSerializer` без config и обычный toArray() сохраняют
receiver как поле. Подробнее — [исходящая проекция](../serialization/receiver-output.md).

Необязательный hydrator описан в [пользовательской гидратации DTO](hydrators.md); null по умолчанию сохраняет штатную гидратацию.
