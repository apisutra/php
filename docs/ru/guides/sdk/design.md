<!-- languages --> <a href="../../../en/guides/sdk/design.md">English</a> · <a href="design.md">Русский</a> <!-- /languages -->
# Структура SDK и владение типами <a id="section-1"></a>

Цель — получить предсказуемую структуру SDK‑клиента с **минимальным** бойлерплейтом,
максимально используя возможности пакета, его паттерны и современные стандарты
кодирования — без рассинхрона между запросами, DTO и политиками выполнения.

## Входной артефакт <a id="section-2"></a>
Начинайте с карты решений (см. [Анализ провайдера](analysis.md)).
Дальше используйте эту карту как источник требований для структуры SDK.
Для пошаговой работы используйте [Чеклист разработки провайдера](coverage.md).

## Карта решений → единая модель <a id="section-3"></a>
Зафиксируйте и **централизуйте** то, что должно быть единым:
- enums для ключей операций, статусов, типов сущностей и ошибок
- модель ошибок и правила маппинга в SDK
- стандартизированные DTO и форматы полей
- базовые правила пагинации и меты
- логическую группировку запросов по ресурсам

Рекомендация: **сразу** добавляйте `title()` во все enum, которые участвуют в ответах/запросах.
Это упрощает UX, делает сериализацию предсказуемой и исключает доработки при включении
`DtoSerializationProfile` и request-level enum policy.
`title()` должен возвращать человекочитаемое название **текущего** значения enum.

**Рекомендация:** централизуйте правила по направлениям:
- hydration DTO — через `DtoHydrationProfile` либо [внешний `HydrationRules`](../dto/plain-models.md) для моделей без атрибутов
- DX DTO serialization — через `DtoSerializationProfile`
- wire body semantics — через `ClientConfig::wireBodySerializationPolicy`
- request/query/header/path semantics — через `ClientConfig`
Для body DTO рекомендуемый default:
- `enumOutput: EnumOutput::TitleValueString`
- `strictEnums: false`

Это даёт читаемый формат `title|value`, а при отсутствии `title()` использует безопасный
fallback без жёсткой ломки SDK.

Практический шаблон:
```php
final readonly class ProviderDtoSerializationProfile implements DtoSerializationProfileInterface
{
    public function policy(): DtoSerializationPolicy
    {
        return new DtoSerializationPolicy(
            enumOutput: EnumOutput::TitleValueString,
            strictEnums: false,
            namingStrategy: NamingStrategy::SnakeCase,
            serializeNulls: false,
        );
    }

    public function casts(): array
    {
        return [];
    }
}
```

## Базовые классы вместо копипаста <a id="section-4"></a>
Создайте свои base‑классы, которые задают дефолты и правила поведения.
Это основная точка снижения бойлерплейта.

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Core\AbstractClient;

final class ProviderClient extends AbstractClient
{
    public function __construct(TransportInterface $transport)
    {
        parent::__construct(
            new ClientConfig(
                baseUrl: 'https://api.example',
                dtoSerializationProfile: new ProviderDtoSerializationProfile(),
                throwOnErrors: false,
            ),
            $transport,
        );
    }
}
```

Рекомендуемые базовые классы для атрибутной модели SDK:
- `BaseClient` — дефолтный `ClientConfig`, общие политики
- `BaseRequest` — общие правила сериализации/валидации/опций
- `BaseResource` — единая навигация и группировка запросов
- `BaseDto` — общий формат для входных DTO и binding `DtoHydrationProfile` / `DtoSerializationProfile`
- `BaseResponseDto` — единые соглашения по DTO‑ответам и binding `DtoHydrationProfile` / `DtoSerializationProfile`

### Рекомендация <a id="section-5"></a>

Сначала создайте свои базовые абстракции от `AbstractRequest`/`AbstractDto` и т.д.,
а затем наследуйте конкретные запросы и DTO уже от этих базовых классов.
Так вы централизуете кастомные задачи и правила в одном месте,
без правок десятков классов.

Для атрибутных DTO рекомендуемый pattern:
- `BaseDto` / `BaseResponseDto` привязаны к `DtoHydrationProfile`
- `BaseDto` / `BaseResponseDto` привязаны к `DtoSerializationProfile`
- `ClientConfigFactory` при необходимости передаёт профиль сериализации в `ClientConfig::dtoSerializationProfile`; входящий профиль остаётся привязан к классу
- конкретные DTO обычно не размечаются дополнительно
- class-level `#[DtoHydrate(...)]` / `#[DtoSerialize(...)]` используются только для редких override

Для plain-моделей базовые DTO ApiSutra и атрибуты не нужны. Соберите один
`HydrationRules` в factory, подключите как `HydrationConfig(rules: $rules)`, а вне
клиента — в `Hydrator::forRules()`. `DTO::from()` набор не наследует. Не совмещайте
`DtoRules` с профилем гидратации одного класса; [правила конфликтов](../../reference/dto/field-rules.md#section-3)
проверяются при создании гидратора. Рекомендуемое имя приёмника неизвестных полей —
`_extra`; клиент с набором исключает его из запросов.

Допустимый pattern для DTO inheritance:
- базовый DTO держит constructor-backed общие поля
- конечный DTO может добавлять public hydrated properties без собственного конструктора
- hydrator поддерживает такой shape через constructor-first + fallback assignment remaining properties

Если провайдер системно использует `''` как "значения нет", не нормализуйте это вручную по всем DTO.
Используйте:
- property-level `EmptyStringAsNull` для точечных случаев
- hydration profile-level `emptyStringBehavior` для централизованной политики

Подробности и примеры: [DTO](../dto/attribute-models.md).

Если одно и то же тело запроса используется в нескольких запросах — вынесите его в DTO
и используйте в запросах как свойство (`Body DTO`). См. [Requests](../requests.md).

Если у провайдера много эндпоинтов, заранее разложите/сгруппируйте по логическому признаку их по ресурсам —
это улучшит навигацию и сделает API клиента читаемым. См. [Resources](../../reference/client/resources.md).

## Структура и владение сущностями <a id="section-6"></a>

Этот раздел описывает SDK конкретного провайдера. Структура внутренних модулей
ядра ApiSutra определяется их механизмами и контрактами.

### Обязательные границы владения <a id="section-7"></a>

- Запрос и его собственные DTO/enum принадлежат одному методу ресурса и хранятся вместе.
- Типы, используемые несколькими методами одного ресурса, остаются в этом ресурсе.
- `Domain/Dto` и `Domain/Enums` предназначены для типов уровня всего провайдера.
  Не превращайте общий слой в склад типов без определённого владельца.
- `Client` отвечает за сборку настроек и точку входа. Auth, pagination и error mapping
  остаются отдельными механизмами, которые передаются в конфигурацию.

### Каноническая структура endpoint (настоятельная рекомендация) <a id="section-8"></a>

Предпочитайте группировку вокруг запроса. Создавайте только те каталоги и базовые
классы, которые нужны используемым возможностям SDK:

```text
Base/
  BaseClient.php
  BaseRequest.php
  BaseResource.php
  BaseDto.php
  BaseResponseDto.php
Client/
  ProviderClient.php
  ProviderClientConfig.php
Auth/
Pagination/
Errors/
Domain/
  Dto/
  Enums/
Resources/
  Users/
    UsersResource.php
    Common/
      Dto/
      Enums/
    Requests/
      ListUsers/
        ListUsersRequest.php
        Dto/
          Request/
          Response/
        Enums/
tests/
  Unit/
    Resources/
      Users/
        Requests/
          ListUsers/
            ListUsersTest.php
```

`Common/` нужен только для типов, используемых несколькими методами ресурса.
В корне ресурса остаются сам ресурс и общие для него компоненты.

Не разносите один метод по параллельным деревьям вроде
`Requests/ListUsersRequest.php` и `ListUsers/Dto/`: запрос, его DTO и enum
должны оставаться в одном поддереве `Requests/ListUsers/`.

### Рекомендации для больших моделей <a id="section-9"></a>

- Для небольшого ресурса допустима простая структура с небольшим числом DTO.
- Когда файлов становится много, группируйте их по endpoint, сценарию или
  смысловым блокам ответа. То же относится к enum.
- Для сложного ответа внутри `Requests/<Method>/Dto/Response/` можно выделить,
  например, `Main/`, `Rights/`, `Bounds/`, `History/`, `Restrictions/`.
- Подгруппы ресурсов вводите по бизнес-контексту и навигации API клиента.
  Глубина вложенности и число файлов сами по себе не требуют новых слоёв.

О навигации между ресурсами и привязке запросов к клиенту см. [Resources](../../reference/client/resources.md).

## Где задавать правила <a id="section-10"></a>

Распределяйте правила по уровню ответственности:
- `ClientConfig` — глобальные дефолты (retry, rate‑limit, cache, timeouts)
- `DtoHydrationProfile` — входные правила атрибутных DTO (`from()` и pipeline)
- `HydrationRules` — входные правила plain DTO, strict, формы и остаток данных; [границы исходящих запросов](../../reference/serialization/receiver-output.md#section-1)
- `DtoSerializationProfile` — DX DTO semantics (`toArray()`, enum output, DTO naming, null policy)
- `ClientConfig::wireBodySerializationPolicy` — transport body semantics
- атрибуты запроса — специфика конкретного endpoint
- runtime‑опции — разовые переопределения на вызов

Практическое правило:
- DTO hydration contract задаётся через `DtoHydrationProfile` либо внешний `HydrationRules`
- DX DTO contract задаётся через `DtoSerializationProfile`
- wire body contract задаётся через `ClientConfig::wireBodySerializationPolicy`
- request/query/header/path contract задаётся через `ClientConfig`
- профиль сериализации можно передавать в `ClientConfig`; набор гидратации передавайте явно клиенту и standalone-гидратору

Если провайдер требует служебные креды в `body/query/form`, задавайте их
централизованно через `ClientConfig::credentialsConfig`.
Это устраняет копипасту по request-классам и делает merge‑политику детерминированной.
