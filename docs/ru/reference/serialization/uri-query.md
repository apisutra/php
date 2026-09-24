<!-- languages --> <a href="../../../en/reference/serialization/uri-query.md">English</a> · <a href="uri-query.md">Русский</a> <!-- /languages -->
# URI, path и query <a id="section-1"></a>

Передайте готовую ссылку через `$request->withUrl($url)`. SDK использует её целиком,
без base path/query клиента. Дополнительных настроек для обычной подписанной ссылки
не нужно. Абсолютный `getEndpoint()` имеет тот же контракт.

```php
declare(strict_types=1);

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\File;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Http\FileFormat;
use ApiSutra\VO\Files\FileInput;

#[Post('/upload-sessions')]
final class CreateUploadSessionRequest extends AbstractRequest {}

// Пример API, которое возвращает URL для POST multipart.
#[Post('/upload')]
final class UploadToSessionRequest extends AbstractRequest
{
    /** @param list<FileInput> $files */
    public function __construct(
        #[File(name: 'file', format: FileFormat::Multipart)]
        public array $files,
    ) {}
}

$session = (new CreateUploadSessionRequest())->setClient($client)->dataOrFail();
$upload = new UploadToSessionRequest([FileInput::fromPath('/tmp/report.pdf')]);
$result = $upload->setClient($client)->withUrl($session['url'])->send()->raw();
```

Метод, формат тела и нужные заголовки задаются по контракту провайдера. Если сессия
возвращает специальные заголовки, задайте их через `withHeader()`. Сохранение URL
не исправляет неверные method/body/signed headers и не означает проверку подписи SDK.
Binary/multipart upload и download поддерживают [потоковую передачу](../../guides/recipes/files.md),
включая сохранение в путь или пользовательский поток.

## Правила готового адреса <a id="section-2"></a>

- Приоритет: `withUrl()` → абсолютный endpoint → относительный endpoint с effective base URL.
  Новый `withUrl()` заменяет предыдущий; `withoutUrl()` очищает только этот override.
  Ранее заданный `withBaseUrl()` снова действует после очистки. Исходный запрос не меняется.
- Поддерживаются HTTP/HTTPS, ASCII/punycode host и IPv6. URL должен быть уже закодирован.
  Userinfo, другие схемы, `//host`, пробелы, управляющие символы и неверные `%`-последовательности
  вызывают `configuration_error`. Unicode в path/query передаётся percent-encoded.
- Path/query сохраняются: `%2f`, `%2F`, `+`, `%20`, повторяющиеся ключи, порядок,
  пустой `?` и граничные `&`. Fragment удаляется; пустой path становится `/`.
  Штатный cURL-адаптер также сохраняет dot-segments без разрешения `..`.
- Base URL, placeholders относительного endpoint и дополнительные query не применяются.
  Если поля, pagination, continuation или явно включённый enricher создают query-пару,
  SDK возвращает `configuration_error`, а не игнорирует её. Пропущенный null/пустой список
  пары не создаёт; включённый null и пустая строка создают пару и конфликтуют.
- Query auth не может дописывать ключ в готовый URL, даже при явном включении.
  Динамические query для обычного API собирайте через относительный endpoint.

## Кеш, redirects и диагностика <a id="section-3"></a>

Полный URL автоматически обходит HTTP cache. Явное включение кеша, включая атрибут
запроса, вызывает `configuration_error`: SDK не знает срока действия подписи.
Существующая семантика custom key обычных запросов сохранена.

Штатный адаптер не следует redirects. Ответ 3xx возвращается в обычную обработку
HTTP-результата; разрешение origin не разрешает переход по `Location`.
Поздняя смена origin через stages/hooks/auth/retry handler, а для готового URL —
любое изменение адреса, отклоняется до cache lookup/HTTP.

В безопасных `requestDebug()`, логах и записанных fixtures готовая ссылка заменяется
на origin с `/[redacted]`. Маскируются также отражённые полная ссылка и request target.
Для этого не нужен `RedactionPolicy`. Дополнительные правила нужны для
других секретных полей API. Исходные `response`, `exception`, данные результата и
`requestDebug(false)` — raw-доступ, их нельзя считать безопасным экспортом.

## Свой транспорт <a id="section-4"></a>

Штатный `HttpTransport::createDefault()` обеспечивает контракт автоматически.
Для своего транспорта/PSR-адаптера см. [DestinationAwareInterface](../execution/transport.md#section-11).
Отсутствие поддержки даёт `configuration_error` до auth/refresh и отправки защищаемого запроса.
Синхронный и promise-интерфейсы используют те же правила.

Внешнему назначению нужны явные credentials/auth и разрешённый origin.
Для независимого сервиса создайте отдельный клиент с его base URL и credentials.
Для полного URL используйте `withUrl()`. Относительные запросы к исходному API
клиента используют его обычные настройки.

## URI и path <a id="section-5"></a>

Endpoint задаётся относительно `baseUrl`: `https://api.test/v1` + `/users` или
`users` даёт `https://api.test/v1/users`. Ведущий slash не сбрасывает base path.
Network-path (`//host/...`), неподдерживаемые схемы,
управляющие символы, неэкранированные пробелы, обратный slash и сегменты `.`/`..`
отклоняются с `configuration_error` до авторизации и HTTP. Абсолютный HTTP/HTTPS
endpoint передаётся по [контракту готового URL](uri-query.md).

SDK отделяет query и fragment перед соединением path. Query добавляются в порядке:
base URL → endpoint → сериализованные поля → query auth. Например:

```text
baseUrl:  https://api.test/v1?version=2
endpoint: /users?fixed=%2F&fixed=+#ignored
page:     3
URI:      https://api.test/v1/users?version=2&fixed=%2F&fixed=+&page=3
```

Fragment удаляется. Существующие корректные query-байты не декодируются и не
сортируются: `%2F`, `+`, `%20`, дубликаты и порядок сохраняются. Пустые `?` и
граничные `&` нормализуются. Совпадающие ключи добавляются повторными парами;
их интерпретация зависит от API. Runtime-поля не перезаписывают fixed query.

`{name}` подставляется только в path и принимает исходное значение сегмента.
`a/b +%` становится `a%2Fb%20%2B%25`; значение `%2F` становится `%252F`, а литерал
`%2F` в endpoint сохраняется. Unicode кодируется по UTF-8. `0` допустим;
отсутствующий, null, пустой или равный `.`/`..` параметр, а также незаполненный
placeholder дают `serialization_error` до HTTP. Автоматического перехода по `..` нет.

`withBaseUrl()` сохраняет свой отдельный контракт и не изменяет исходный execution
или конфиг. Для полного внешнего или подписанного адреса используйте `withUrl()`; при смене
origin через `withBaseUrl()` действует [изоляция credentials](uri-query.md).

## Query <a id="section-6"></a>

Без дополнительных настроек boolean передаётся как `true → 1`, `false → 0`.
Это относится также к элементам query-списков и скалярным текстовым полям multipart.
JSON сохраняет boolean; массивы в полях multipart кодируются как JSON.
Необязательный [textBooleanFormat](request-parts.md#section-4)
меняет формат на `true/false`; явный cast отдельного поля имеет приоритет.

| Входное значение | Query |
| --- | --- |
| `null` | Пропущен по умолчанию; при включении — `key=` |
| `false` / `true` | `key=0` / `key=1` |
| `0` / `"0"` | `key=0` |
| Пустая строка | `key=` |
| Пустой список | Пропущен во всех форматах |
| Плоский список скаляров и null | Сохранены порядок, дубликаты и пустая позиция для null |
| Ассоциативный, разреженный или вложенный массив | `serialization_error` до HTTP |

`#[Query(nullable: null)]` и отсутствие `nullable` наследуют `ClientConfig::serializeNulls`
(по умолчанию false). Явные true/false на поле перекрывают настройку клиента.
Это правило относится к верхнему уровню: null внутри списка сохраняет позицию.
Включённый null и пустая строка имеют одинаковое представление `key=`.

```php
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Enums\Http\QueryArrayFormat;

#[Query('q')]
public string $query;

#[Query('ids', arrayFormat: QueryArrayFormat::Comma)]
public array $ids;
```

Нюансы:
- `arrayFormat` задаёт формат массива
- `nullable` в `#[Query]` управляет включением `null`
- глобально `serializeNulls` действует для body и query

### Формат массивов в query <a id="section-7"></a>
`QueryArrayFormat` относится **только** к query‑строке и формирует
`a[]=1&a[]=2`, `a=1%2C2%2C3` и т.п. JSON‑строку он не создаёт.
Brackets использует `a[]`, Indices — `a[0]`, Repeat — повторяющийся `a`,
Comma — одну строку с URL-кодированными запятыми. Comma отклоняет элементы,
содержащие запятую: используйте другой формат или явный cast по контракту API.
Для структур используйте `JsonCast` либо отдельные именованные Query-поля.

```php
#[Query('regions', arrayFormat: QueryArrayFormat::Comma)]
public array $regions;
```

### JSON‑строка вместо массива <a id="section-8"></a>
Если API ожидает строку вида `"[1,2,3]"` (в query или body), используйте `JsonCast`
или храните строку вручную:
```php
use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Casts\JsonCast;

#[Query('regions')]
#[Cast(JsonCast::class)]
public array $regions = [1, 2, 3];

// или строка вручную
#[Query('regions')]
public string $regions = '[1,2,3]';
```

## Path <a id="section-9"></a>
```php
use ApiSutra\Attributes\Request\Path;

#[Path('id')]
public int $userId;
```

Если имя свойства совпадает с placeholder в endpoint, `#[Path]` не обязателен:
`{id}` автоматически свяжется с `$id`.
Атрибут нужен, когда имена различаются.

## Как выбирается endpoint и baseUrl <a id="section-10"></a>
**Endpoint:**
1) `resolveEndpoint()` в запросе (если переопределён)
2) атрибут `#[Get('/path')]` и др.

**BaseUrl:**
1) runtime override `withBaseUrl()`
2) `resolveBaseUrl()` запроса
3) `ClientConfig::baseUrl`

Обычно достаточно `ClientConfig::baseUrl`.
Переопределение нужно, если:
- один клиент ходит на несколько доменов/версий API
- нужен временный override в тестах или для отдельных эндпоинтов

## Пользовательское wire-представление <a id="section-11"></a>

- Query и текстовый multipart кодируют false как `0`. Для пустой строки задайте свой
  `CastInterface`, возвращающий `""` для false и `"1"` для true, или готовую строку.
- Пустой список Comma пропускается. Для явного пустого значения передайте пустую
  строку или null с `Query(nullable: true)`.
- Maps, разреженные/вложенные массивы и элементы Comma с запятыми требуют другого
  представления: `JsonCast`, отдельных Query-полей или подходящего формата списка.
- Path-значения должны заполнять все placeholders и не содержать пустых/dot-сегментов.
- Query из base URL и endpoint объединяются с полями; fragment удаляется.
  Для полного подписанного URL с сохранением request target используйте `withUrl()`.
