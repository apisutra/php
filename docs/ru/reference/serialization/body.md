<!-- languages --> <a href="../../../en/reference/serialization/body.md">English</a> · <a href="body.md">Русский</a> <!-- /languages -->
# Тело HTTP-запроса <a id="section-1"></a>

## Request DateTime <a id="section-2"></a>
```php
use ApiSutra\Config\DateTimeSerializationPolicy;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    requestDateTime: new DateTimeSerializationPolicy(
        format: DATE_ATOM,
        timezone: null,
    ),
);
```

- `requestDateTime` применяется к request-level serialization:
  - `query`
  - `header`
  - `path`
  - request body fields, которые не проходят через DTO body serializer
- `timezone` приводит дату к указанной зоне перед форматированием
- `requestDateTime` не задаёт DTO hydration/body semantics; внешние входные правила
  подключаются отдельным `ClientConfig::hydration`

Для union‑полей (`string|DateTimeInterface`) ветка выбирается по runtime‑значению,
а не по порядку типов в объявлении свойства.

Сохранение больших целочисленных JSON-идентификаторов включено автоматически.
Параметров ClientConfig для этого не требуется: DTO провайдера выбирает string
или int|string; переполнение int даёт ошибку. Контракт —
[большие целые в ответах](../dto/scalars.md#section-4).

Подробное описание поведения: [Сериализация запросов](request-parts.md).

## Request-level enum serialization <a id="section-3"></a>

Для query/header/path оставляйте enum policy в клиенте.

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Serialization\EnumOutput;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    dtoSerializationProfile: $dtoProfile,
    wireBodySerializationPolicy: new DtoSerializationPolicy(enumOutput: EnumOutput::Value),
    requestPartsEnumOutput: EnumOutput::Value,
);
```

Рекомендация:
- DX / `toArray()` → `DtoSerializationProfile`
- wire body → `wireBodySerializationPolicy`
- query/header/path → request-level config

Подробное: [Сериализация запросов](request-parts.md).

## Body и nested <a id="section-4"></a>
```php
use ApiSutra\Attributes\Request\Body;

#[Body('payload.user')]
public array $user;
```

`nested` поддерживает dot‑paths. Если `nested` пустой, используется имя поля.

## Root body (JSON Patch / bulk) <a id="section-5"></a>
```php
use ApiSutra\Attributes\Request\BodyRoot;

#[BodyRoot]
public array $operations; // [{...}, {...}]
```

В этом режиме значение свойства становится корнем body (например `[...]`), а не оборачивается в объект.

## Ошибки кодирования JSON <a id="section-6"></a>

Исходящее JSON-тело, Base64 JSON, JSON-значения multipart и `JsonCast::serialize()`
кодируются строго. Невалидный UTF-8, INF/NAN, неподдерживаемые значения и циклические
структуры дают `SerializationException` и `serialization_error` до HTTP-вызова.
При ошибке `json_encode` исходный `JsonException` доступен как `previous`.
Для обхода вложенных массивов действует предел 512 уровней; он также останавливает
циклические массивы до финального кодирования. Для графа DTO проверяются
повторное вхождение объекта в текущую ветку и предел глубины; повторное использование
одного DTO в независимых полях разрешено.

SDK не исправляет и не подставляет данные молча. JSON `false` и `[]` сохраняются;
query остаётся отдельной частью запроса. Binary и обычные текстовые multipart-поля
не проверяются как JSON. Ошибки доставки через result/исключения описаны в
[руководстве по ошибкам](../results/handles.md).

## Потоковое файловое тело <a id="section-7"></a>

Binary и multipart передаются через `PreparedRequest.stream` с текущей позиции
источника; binary не помещает файл в `PreparedRequest.body`.
Base64 JSON остаётся форматом с полной материализацией. Детали —
[файлы](../../guides/recipes/files.md).
