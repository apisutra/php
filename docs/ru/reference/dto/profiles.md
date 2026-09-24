<!-- languages --> <a href="../../../en/reference/dto/profiles.md">English</a> · <a href="profiles.md">Русский</a> <!-- /languages -->
# Имена полей и профили гидратации <a id="section-1"></a>

## Маппинг полей <a id="section-2"></a>
Когда ключи ответа/запроса отличаются от имени свойства.
- `Map` — когда нужен один и тот же внешний ключ и для гидрации, и для сериализации.
- `From` — для входящих данных (ответ API).
- `To` — для исходящих данных (сериализация запроса).
```php
use ApiSutra\Attributes\DataTransfer\Map;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\To;

#[Map('user_id')]
public int $userId;

#[From('data.user_id')]
public int $id;

#[To('user_id')]
public int $id;
```

### Что использовать <a id="section-3"></a>

| Сценарий | Что использовать | Почему |
|---|---|---|
| Один и тот же внешний ключ нужен и на вход, и на выход | `#[Map('user_id')]` | Убирает дублирование `From` + `To` |
| Нужен только входящий mapping | `#[From('data.user_id')]` | Поддерживает dot-path и fallback |
| Нужен только исходящий mapping | `#[To('user_id')]` | Явно управляет сериализацией |
| Вход и выход отличаются | `#[From(...)]` + `#[To(...)]` | Направления независимы |
| Есть `Map`, но одно направление надо переопределить | `Map` + `From` или `Map` + `To` | `From` имеет приоритет при hydrate, `To` — при serialize |
| Обычный camelCase <-> snake_case без исключений | `NamingStrategy::SnakeCase` | Не нужны явные атрибуты |

Приоритеты:
- hydrate: `From` -> `Map` -> `NamingStrategy`
- serialize: `To` -> `Map` -> `NamingStrategy`

## Профиль атрибутной модели <a id="section-4"></a>

`DtoHydrationProfileInterface` находится в `Contracts\Interfaces\Serialization`.
Он возвращает `DtoHydrationPolicy` через `policy()` и casts по PHP-типам через `casts()`.
`#[DtoHydrationProfile(Profile::class)]` связывает профиль с DTO или его общей базой;
класс профиля создаётся без аргументов. Поиск идёт от конкретного класса к родителям:
выбирается ближайший профиль, а отдельно — ближайший `DtoHydrate` override.

`DtoHydrationPolicy` по умолчанию задаёт `NamingStrategy::None`,
`EmptyStringBehavior::Keep` и стандартную DateTimeHydrationPolicy. `DtoHydrate`
заменяет только ненулевые параметры поверх найденного профиля. Общие базы
AbstractDto/AbstractResponseDto сами не подключают профиль.

Casts профиля принимают экземпляр направленного HydrationCastInterface/SerializationCastInterface либо класс с конструктором без
аргументов. Атрибут Cast поля имеет приоритет перед cast профиля. Глобальный и
клиентский CastRegistry не используются для входной гидратации.
[Полный выбор преобразования](../serialization/casts.md).

При внешнем наборе RulePolicy задаётся без атрибутов. Сочетание DtoRules с входным
профилем класса запрещено; класс вне DtoRules сохраняет свой профиль целиком и
игнорирует defaults набора. [Конфликты и приоритеты](field-rules.md).

## Политика входных дат <a id="section-5"></a>

`DateTimeHydrationPolicy` находится в `ApiSutra\Config`.

| Параметр | Default | Значение |
| --- | --- | --- |
| `format` | `DATE_ATOM` | Ожидаемый формат даты |
| `defaultTimezone` | `'UTC'` | Зона для значения без offset |
| `preserveOffset` | `true` | Сохранять offset исходного значения |
| `strictMissingTimezone` | `false` | Требовать явно заданную зону |
| `strictFormat` | `false` | Требовать соответствия формату |
| `invalidBehavior` | `DateTimeInvalidBehavior::Throw` | Реакция на неразбираемую дату |

Точечный DateTimeFrom имеет приоритет над политикой даты. Обработка union не должна
использоваться как способ скрыть неверный формат: [выбор типов](scalars.md).
Исходящие даты описаны отдельно в [DTO output](../serialization/dto-output.md).

## Разбор входной даты <a id="section-6"></a>
Поля `DateTimeInterface` парсятся по правилам hydration policy:
- `format` — приоритетный формат для `createFromFormat`
- если `format` не подошёл:
  - `strictFormat=true` → ошибка (без fallback)
  - `strictFormat=false` → fallback на `DateTimeImmutable($value, $defaultTimezone)`
- `defaultTimezone` используется **только** если вход без оффсета
- `preserveOffset=true` сохраняет оффсет из строки; `false` — приводит к `defaultTimezone`
- `strictMissingTimezone=true` — отсутствие оффсета считается ошибкой
- `invalidBehavior` управляет реакцией на ошибку:
  - `Throw` — исключение
  - `Null` — вернуть `null`

Результат `invalidBehavior=Null` должен допускаться native-типом поля. Иначе
проверка после cast даёт `HydrationException` с reason `null_not_allowed`. DefaultValue
применяется до преобразования и повторно для получившегося null не запускается.
