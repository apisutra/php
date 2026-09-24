<!-- languages --> <a href="../../../en/reference/client/operation-inventory.md">English</a> · <a href="operation-inventory.md">Русский</a> <!-- /languages -->
# Инвентаризация операций SDK <a id="section-1"></a>

Read-only inventory of provider SDK operations, built from request introspection.

## Зачем это нужно <a id="section-2"></a>
Этот слой нужен, когда SDK должен уметь:
- получить список всех декларативно описанных операций;
- отдать method/endpoint/responseType по request-классам;
- отдать стабильные SDK call paths вида `v1()->objects()->getByCadnum()`;
- собрать единый DX snapshot без ручного агрегатора в каждом provider package;
- использовать request metadata в tooling, docs, admin UI и внутренних справочниках.

Если provider SDK начинает вручную писать код для:
- scan request classes
- resolve `RequestSpec`
- normalize operation metadata
- filter by request/endpoint
- восстанавливать `resource()->method()` через локальную reflection-логику

это хороший сигнал использовать `OperationInventory`.

## Что это такое <a id="section-3"></a>
`OperationInventory` строится на основе:
- `RequestScanner`
- `RequestSpecResolver`
- `OperationDescriptor`

Это не runtime слой:
- не использует `ExecutionResult`
- не зависит от transport/pipeline
- не делает I/O

Это не provider catalog:
- inventory — introspection layer поверх request-классов
- catalogs — отдельный aggregated static knowledge layer SDK

## Базовый API <a id="section-4"></a>
- `OperationInventoryInterface`
- `OperationDescriptorView`
- `OperationInventoryBuilder`

Минимальный `OperationDescriptorView`:
- `requestClass`
- `httpMethod`
- `endpoint`
- `responseType`
- `operationDescriptor`
- `hasDownload`
- `hasNoAuth`
- `skipCredentialsEnrichment`
- `sdkCallPaths`
- `continuationFinalType` — финальный DTO для async-цепочки (`#[ContinuationResult(finalType: ...)]`)
- `continuationUnwrap` — путь к финальному payload внутри envelope для await
- `pollRequestClass` — класс poll-запроса (если задан в `ContinuationResult`)
- `returnsUnwrap` — путь к sync payload внутри envelope (`Returns::unwrap`)
- `resourcePath` / `resourceLabel` — иерархия ресурсов (см. `ResourceNameResolverInterface`)

Convenience accessors:
- `title()`
- `description()`
- `note()`
- `isAsync()` — true, если задан `#[ContinuationResult]`

## Доступ <a id="section-5"></a>
На уровне клиента:
- `$client->operationInventory()`

Из inventory:
- `all()`
- `forRequest(FQCN)`
- `forEndpoint('/path')`

## Пример <a id="section-6"></a>
```php
$inventory = $client->operationInventory();

$all = $inventory->all();
$byRequest = $inventory->forRequest(GetHistoryRequest::class);
$byEndpoint = $inventory->forEndpoint('/rights/history');
$paths = $byRequest?->sdkCallPaths ?? [];
```

Если request-класс имеет:
```php
#[OperationDescriptor(
    title: 'Get history',
    description: 'Returns rights history for the object.',
    note: 'Paid operation.',
)]
```

то inventory отдаст эти данные через `operationDescriptor`, `title()`,
`description()` и `note()`.

Для SDK call paths:
- `sdkCallPaths` — это список стабильных публичных entrypoint-путей от корня клиента;
- формат строки: `resource()->method()` или `v1()->resource()->method()`;
- список dedupe-нут и отсортирован лексикографически;
- порядок не означает “primary path” против “alias path”.

Пример:
```php
$operation = $client->operationInventory()->forRequest(GetByCadnumRequest::class);

// [
//     'objects()->getByCadnum()',
//     'v1()->objects()->getByCadnum()',
// ]
$paths = $operation?->sdkCallPaths ?? [];
```

## Как строятся sdkCallPaths <a id="section-7"></a>
`sdkCallPaths` резолвятся только при сборке inventory для конкретного клиента:
- через `$client->operationInventory()`;
- через `OperationInventoryBuilder::buildForClient(...)`.

Для `OperationInventoryBuilder::buildForRootNamespace(...)` поле всегда будет пустым:
- `sdkCallPaths: []`

Причина простая: без concrete client class introspection-слой не может
контрактно доказать стабильный SDK entrypoint path.

## Поддерживаемые паттерны <a id="section-8"></a>
- публичные client/resource/router методы с declared return type;
- цепочки `client -> resource -> request`;
- alias-методы, если они сами публичны и возвращают тот же request-класс;
- version shortcut methods вида `v1()`, `v2()`, `v3()`;
- version-aware routing через `requestByVersion([...])` и `resourceByVersion([...])`,
  когда путь можно статически сузить до конкретного request-класса.

## Что не поддерживается <a id="section-9"></a>
- parameterized selectors вида `useVersion(...)` как stable path;
- магия через `__call`;
- методы без declared return type;
- runtime-only построение цепочек;
- методы, которые возвращают `ResultHandle`, `ResolvedResultInterface`, DTO,
  scalar и другие не-request entrypoints.

## Важные ограничения <a id="section-10"></a>
- inventory использует **declarative request spec**, а не runtime override
- если request меняет endpoint через `resolveEndpoint()`, inventory показывает
  именно декларативный endpoint из атрибутов
- `sdkCallPaths` тоже относятся к declarative introspection и не отражают runtime override
- если version-dependent метод нельзя сузить до одного request-класса без runtime контекста,
  path не экспортируется
- inventory не заменяет provider catalogs
- inventory не строит resource tree как first-class модель

## Когда использовать inventory, а когда catalog <a id="section-11"></a>
Используйте `OperationInventory`, когда нужен:
- универсальный снимок всех request-операций SDK
- introspection по method/endpoint/request class
- стабильный список SDK call paths для request-класса
- DX/tooling слой поверх request-классов

Используйте `ProviderCatalog*`, когда нужен:
- pricing catalog
- capability catalog
- operation descriptor catalog как самостоятельный static dataset
- request-bound или domain-bound knowledge layer, не сводимая к одному `RequestSpec`
