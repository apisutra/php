<!-- languages --> <a href="../../../en/reference/execution/pagination.md">English</a> · <a href="pagination.md">Русский</a> <!-- /languages -->
# Пагинация <a id="section-1"></a>

Дефолтные правила пагинации в `ClientConfig::paginationRule`.

## Базовая настройка <a id="section-2"></a>
```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Pagination\PaginationRule;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    paginationRule: PaginationRule::all(),
);
```

По умолчанию `paginationRule = PaginationRule::single()` — пагинация не включается,
пока вы не зададите правило или не вызовете `paginate()`.

## Переопределение на запросе <a id="section-3"></a>
- Атрибут `#[Pagination]` — конфигурация пагинации (пути, поля).
- Runtime‑override: `rules(PaginationRule::pages(2))`.

## PaginationConfig (детали) <a id="section-4"></a>
```php
use ApiSutra\Config\PaginationConfig;

$config = $config->with(paginationConfig: new PaginationConfig(
    pageParam: 'page',
    limitParam: 'per_page',
    cursorParam: 'cursor',
    metaPath: 'meta',
    itemsPath: 'data.items',
    offsetBased: false,
    maxPages: 500,
));
```

Дефолты `PaginationConfig` (если не задан):
- `pageParam = page`, `limitParam = limit`, `cursorParam = null`
- `metaPath = meta`, `itemsPath = data`
- `offsetBased = false`, `maxPages = 1000`

Ключевые поля:
- `itemsType` — тип элемента для коллекции
- `itemsCollection` — класс коллекции items
- `itemsCollectionFactory` — фабрика коллекции items
- `metaResolver` — собственный резолвер меты

Рекомендация:
- если meta‑поля нестандартны — выделите класс `PaginationMetaResolverInterface`
- если нужен типизированный набор items — используйте `itemsCollection` или `itemsCollectionFactory`
- если хотите вернуть обёртку с meta+items — используйте DTO‑контейнер на базе `AbstractPaginationContainerDto`

`itemsCollection` подходит, если коллекцию можно создать через `fromArray()`/`make()`/`__construct`.
`itemsCollectionFactory` используйте для сложной инициализации (зависимости, валидация).

Практическая [конфигурация провайдера](../../guides/recipes/pagination.md#section-12)
показывает применение этих параметров.

## Offset‑based <a id="section-5"></a>
Если `offsetBased = true`, `page` превращается в offset.
Требуется `limit`, иначе будет исключение.

Пагинация доступна для запросов, реализующих `PaginableInterface`
(обычно через `AbstractPaginatedRequest`).

## Базовая структура <a id="section-6"></a>
```php
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/users')]
final class ListUsers extends AbstractPaginatedRequest {}
```

## Выполнение <a id="section-7"></a>
```php
$result = (new ListUsers())->paginate()->all();
$result = (new ListUsers())->paginate()->pages(3);
$result = (new ListUsers())->paginate()->range(2, 5);
```

## Настройка схемы пагинации <a id="section-8"></a>
```php
use ApiSutra\Attributes\Behavior\Pagination;

#[Pagination(
    pageParam: 'page',
    limitParam: 'per_page',
    itemsPath: 'data.items',
    metaPath: 'meta',
    offsetBased: true,
    cursorParam: 'cursor',
    maxPages: 100,
)]
final class ListUsers extends AbstractPaginatedRequest {}
```

Дополнительные поля:
- `itemsType` — тип элемента для коллекции
- `itemsCollection` — класс коллекции items
- `itemsCollectionFactory` — фабрика коллекции items

## Правила исполнения (PaginationRule) <a id="section-9"></a>
`single()`, `all(failStrategy, concurrency)`, `pages(count, failStrategy, concurrency)` и
`range(from, to, failStrategy, concurrency)` — неизменяемые правила. Defaults: FailAll и concurrency=1.
Атрибут/config схемы описывает провайдера; политика исполнения принадлежит правилу.

## PaginationMeta и резолв <a id="section-10"></a>
Метаданные извлекаются в приоритете:
1) `PaginationMetaOverrideInterface` на запросе
2) `PaginationConfig::metaResolver` (класс или инстанс)
3) `DefaultPaginationMetaResolver`

`PaginationMeta` содержит `total`, `currentPage`, `perPage`, `hasMore`, `nextCursor`.

По умолчанию `DefaultPaginationMetaResolver` ищет поля:
- `total` / `count`
- `page` / `currentPage` / `current_page`
- `per_page` / `perPage` / `limit` / `page_size`
- `next_cursor` / `nextCursor`
- `has_more` / `hasMore`

Если ответ не содержит currentPage, стандартный resolver берёт страницу из runtime-параметров
исполнения; в offset-режиме вычисляет её по offset/limit. Явные метаданные ответа приоритетнее.
Без достаточных runtime-параметров остаётся страница 1.
Если мета лежит в других полях или нужна дополнительная логика — нужен кастомный резолвер.

### Кастомный meta‑resolver <a id="section-11"></a>
Если поля meta нестандартны, выделите свой `PaginationMetaResolverInterface`:
```php
use ApiSutra\Contracts\Interfaces\Pagination\PaginationMetaResolverInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Config\PaginationConfig;
use ApiSutra\VO\Metadata\PaginationMeta;
use ApiSutra\VO\Pipeline\PipelineContext;

final class CustomPaginationMetaResolver implements PaginationMetaResolverInterface
{
    public function resolve(
        AbstractRequest $request,
        array $response,
        array $meta,
        PaginationConfig $config,
        ?PipelineContext $context
    ): PaginationMeta {
        return new PaginationMeta(
            total: isset($meta['count']) ? (int) $meta['count'] : null,
            currentPage: (int) ($meta['page'] ?? 1),
            perPage: (int) ($meta['rows'] ?? 0),
            hasMore: (bool) ($meta['has_more'] ?? false),
            nextCursor: null,
        );
    }
}

#[Pagination(metaResolver: CustomPaginationMetaResolver::class)]
final class ListUsers extends AbstractPaginatedRequest {}
```

### Типизация items и коллекция <a id="section-12"></a>
Если нужен типизированный набор и своя коллекция:
Используйте `itemsCollection` для класса с `fromArray()`, `make()` или конструктором от items.
`itemsCollectionFactory` реализует `PaginationItemsCollectionFactoryInterface::make(array): array|object`
и имеет приоритет. Без обоих полей агрегат содержит массив.
[Рецепт провайдера](../../guides/recipes/pagination.md#section-12) показывает полную фабрику.

## Контейнер с meta + items <a id="section-13"></a>
Чтобы сохранить мету в DTO‑обёртке, используйте:
- `PaginationItemsContainerInterface`
- или `AbstractPaginationContainerDto`

Тогда пагинатор извлечёт items через `items()` и сможет подставить их обратно.

### DTO‑контейнер для paginated‑ответа <a id="section-14"></a>
Укажите `#[Returns(PagedUsersDto::class)]` на запросе. DTO наследует
`AbstractPaginationContainerDto`, передаёт `items` конструктору родителя и реализует
`withItems(array|object $items): static`, возвращая новый DTO с прежними метаданными.
Каждая страница сохраняет этот DTO; итоговый агрегат извлекает его items.

## PaginatedResult <a id="section-15"></a>
Результат пагинации:
- `items()` — все элементы
- `pages()` — коллекция результатов по страницам
- `meta()` — `PaginationMeta`

## Guard и лимиты <a id="section-16"></a>
- `PaginationConfig::maxPages` — жёсткий лимит страниц
- guard проверяет прогресс page/cursor, чтобы остановить бесконечные циклы

## Ошибки, защитная остановка <a id="section-17"></a>

Ошибка страницы сохраняет исходный SDK-код, HTTP-ответ и context.page. Если первая
страница дала 20 объектов, а следующая вернула 403, агрегат содержит PARTIAL, эти
20 объектов и `forbidden` с настоящим 403.

FailAll останавливает обход. Partial может продолжить page/offset-пагинацию, но
cursor-пагинация останавливается после ошибки: следующего cursor нет. Любой повтор
уже посещённого cursor останавливает обход до повторной отправки. Значение `"0"`
сохраняется. `maxPages=null` отключает количественный предел, сохраняя защиту от цикла;
стандартный maxPages остаётся 1000. Непрозрачный cursor не попадает в context ошибки.

Защитная остановка сообщает `execution_error` с `pagination_stalled` или
`pagination_max_pages_reached`. При throwOnErrors false iterator выдаёт один дополнительный FAILED результат
после успешных страниц и завершается; при true вместо этого бросает исключение; это не HTTP-вызов. Проверяйте `isFailed()` перед
чтением данных страницы. Исходную ошибочную страницу iterator повторно не выдаёт.
`pages(N)` и `range()` сохраняют обычное ограничение выборки без дополнительной ошибки.

## Внешние правила DTO <a id="section-18"></a>

Items-only без Returns гидратирует `itemsType` только при явно ненулевом
`ClientConfig::hydration`, включая пустой `new HydrationConfig()`.
Без блока (`hydration: null`) возвращаются raw-массивы: даже Extras,
RequiredInput или Shape на модели элемента не включают конструкторы и проверки.
Это намеренно: атрибуты действуют при входе в DTO, а raw-путь в DTO не входит.
`$config->with(hydration: null)` возвращает копию к raw, исходный клиент сохраняет
поведение. Returns-контейнер типизирует items и без config; коллекция
или factory применяются на прежних typed-путях. Без `itemsType` новый тип не выводится.

[Конфигурация гидратации](../dto/configuration.md) · [Правила](../dto/field-rules.md).

## Диагностика обхода <a id="section-19"></a>

У каждого обхода свой корень; страницы связаны с ним через `trace.parentExecutionId`.
`PaginatedResult::traceId` относится к корню даже без явного override. Итератор
начинает выполнение при потреблении; освобождение незавершённого генератора
пишет `abandoned`, не запрашивая следующую страницу. Повторный обход получает новый ID.
У агрегата нет снимка HTTP-запроса: читайте результаты страниц в `$result->nested`.
При включённом debug `$page->requestDebug()` маскирует секреты, а `$page->debug?->duration`
показывает время исполнения страницы. Общее время — duration завершающего события audit корня.
Страницы упорядочены по номеру; события audit/log сохраняют порядок исполнения.
Trace и audit доступны без debug; ошибки самого обхода содержат traceId корня.
[Жизненный цикл и поля событий](../results/observability.md#section-6).

## Исполнитель и окончательная выдача <a id="section-20"></a>

Каждая страница идёт через исполнитель клиента с Single и остальными runtime-опциями.
`new Paginator($request, client: $client)` позволяет явно выбрать клиента; параметры
конструктора: request, options, paginationOptions, client.
Оба собирающих входа используют одну каноническую область, извлечение meta и бюджет.
Только ленивый iterator открывает отдельную область обхода.
FAILED бросается при throwOnErrors=true; PARTIAL возвращается в обоих режимах.
Фабрика исключений получает полный агрегат один раз. В iterator отказавшая страница
или guard выдаётся при false, бросается при true; затем обход прекращается.

## Конкурентная загрузка страниц <a id="section-21"></a>

Для независимых page/offset API, с клиентом, привязанным к `ListUsers`:
```php
$result = (new ListUsers())->paginate()->withPerPage(100)->withConcurrency(3)->all();
$range = (new ListUsers())->paginate()->withConcurrency(3)->range(10, 20);
$promise = (new ListUsers())->rules(PaginationRule::all(concurrency: 3))->sendAsync();
$result = $promise->wait()->raw();
```
`send()` и терминалы пагинатора остаются синхронными. Конкурентный HTTP требует
поддерживающий его транспорт (штатный Guzzle подходит); sync-only PSR-18 даёт
configuration_error до auth/HTTP bootstrap. Воркеры и новые настройки не нужны.

Первая выбранная страница запрашивается один раз как bootstrap; range начинает с from,
не с 1. Затем свободные слоты принимают страницы, до concurrency одновременно.
**Конкурентный all требует total и положительный perPage при hasMore=true в bootstrap.**
Иначе используйте последовательный all или явно ограниченные pages/range; они не находят
неизвестный конец. Естественно последнему bootstrap total не нужен. Спекулятивного окна
и эвристики короткой страницы нет. Известный cursor отклоняется до HTTP; обнаруженный
в bootstrap — до остальных страниц. Cursor обходится последовательно.

Верхняя граница all фиксируется после bootstrap и может только уменьшаться.
Известный perPage должен соответствовать запрошенному размеру и не меняться. Если limit
не задан, положительный perPage bootstrap применяется к следующим страницам. API
с конечным диапазоном без метаданных размера должен сохранять свой default стабильным.
Противоречивые page/size дают `pagination_metadata_changed`. Offset требует явный положительный limit.

Items, nested-страницы и ошибки страниц идут по запрошенному номеру; meta берётся от
старшей успешной страницы. События отражают реальный порядок завершения. FailAll прекращает
назначение и дожидается начатого; Partial/IgnoreErrors продолжают независимые страницы,
сохраняя ошибки. Ранний конец/уменьшение total тоже останавливают только новые назначения:
начатые ответы сохраняются; фиксированной границы лишних HTTP на меняющейся выборке нет.
maxPages считает выданные запросы вместе с bootstrap. Guard даёт PARTIAL с items,
FAILED без items. Без ошибок — SUCCESS, в том числе для пустой выборки.
Собирающие терминалы хранят все результаты/items: concurrency ограничивает активную работу,
а не всю память. Pool из N обходов с concurrency M может выполнять N×M страниц одновременно.
Согласованность выборки провайдера и checkpoints остаются ответственностью приложения.

## Копии builder и приоритеты <a id="section-22"></a>

`withPerPage`, `withFailStrategy`, `withConcurrency` возвращают копии; сохраняйте результат.
Приоритет политики: builder → runtime rule → client rule. Rule заменяет политику целиком;
явная concurrency=1 перекрывает default клиента 5. Терминал выбирает mode/range,
не меняя другие builders. `pages(N)` означает N страниц от runtime-начала (default 1);
`range(from,to)` включает обе границы. Неположительные размеры/числа/concurrency,
неверные диапазоны и переполнение — ошибки конфигурации, не пустой успех.
Ленивый `foreach ($request->paginate()->withConcurrency(1) as $page)` последователен;
effective concurrency>1 отклоняется до HTTP. Предзагрузки и накопления страниц нет.

## Общий срок и отмена <a id="section-23"></a>

Собирающие all/pages/range делят бюджет, включая bootstrap, auth, квоты, cooldown,
retry-ожидания и сборку. Это минимум totalTimeoutMs, внешнего withDeadline и parent-budget.
**Три последовательные страницы по 400 мс при totalTimeoutMs=1000 не завершатся
успешно за 1200 мс**: третья даст таймаут, первые две сохранятся в PARTIAL.
При конкурентности учитывается прошедшее время, не сумма длительностей страниц.
Ленивый iterator сохраняет свежий клиентский бюджет на страницу; общий withDeadline
запроса ограничивает весь iterator. Общий trace сам по себе не объединяет время.
Истечение срока прекращает назначения и сохраняет данные с
`timeout / execution_deadline_exceeded / stage`. Проверки не прерывают произвольный PHP callback.
Async-отмена/abandonment используют обычный контракт промиса: активные задачи отменяются,
ресурсы освобождаются, итоговый агрегат не обещан. Отмена не откатывает действия сервера.
См. [исполняемый пример](../../../example/pagination/run.php).

См. [ленивые items и контракты коллекций](pagination-items.md): `Paginator::items()` выдаёт значения, сохраняет DTO и бросает при FAILED даже с throwOnErrors=false. Собирающие терминалы отклоняют повтор строкового ключа вместо затирания.
