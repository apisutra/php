<!-- languages --> <a href="../../../en/reference/client/catalogs.md">English</a> · <a href="catalogs.md">Русский</a> <!-- /languages -->
# Статические каталоги провайдера <a id="section-1"></a>

## Зачем это нужно <a id="section-2"></a>
Этот слой нужен, когда SDK должен отдавать не только runtime-результаты запросов,
но и **заранее известные справочники провайдера**, которые полезны для DX и прикладной логики.

Типовые сценарии:
- показать в приложении список доступных операций провайдера;
- отдать pricing / tariff catalog без сетевого вызова;
- хранить capability matrix метода (`supportsAsync`, форматы, ограничения);
- держать статические словари и дескрипторы схем рядом с SDK-кодом;
- привязать catalog к конкретным request-классам, если знания живут на уровне endpoint.

Идея простая: если данные **не приходят из конкретного HTTP-ответа**, но SDK
должен уметь их стабильно и единообразно отдавать, это хороший кандидат для provider catalog.

## Что это такое <a id="section-3"></a>
Static provider catalog — это заранее подготовленные данные SDK, которые:
- не зависят от конкретного `ExecutionResult`
- не извлекаются из HTTP-ответа
- не выполняют I/O
- живут внутри provider SDK как часть его DX-слоя

Типовые примеры:
- pricing / tariff catalogs
- capabilities catalogs
- operation descriptors
- static dictionaries
- request-bound catalogs для конкретных request classes

## Чем отличаются от runtime meta <a id="section-4"></a>
**Runtime meta**:
- источник: `ExecutionResult`
- инструмент: `ResultMetaExtractorInterface`
- задача: техническая мета конкретного выполнения запроса

**Static catalog**:
- источник: сам SDK
- инструмент: `ProviderCatalogInterface` + `ProviderCatalogRegistryInterface`
- задача: read-only knowledge layer провайдера

Не смешивайте эти слои.

## Базовые контракты <a id="section-5"></a>
- `ProviderCatalogMetaInterface`
- `ProviderCatalogInterface`
- `RequestBoundProviderCatalogInterface`
- `ProviderCatalogRegistryInterface`

Минимальная мета каталога:
- `generatedAt(): DateTimeImmutable`
- `source(): ?string`
- `sourceVersion(): ?string`

`generatedAt` — обязательное поле.

## Инварианты <a id="section-6"></a>
- каталог не делает I/O
- каталог не зависит от transport/pipeline/result lifecycle
- каталог read-only
- core не знает billing/capabilities semantics; это остаётся в provider package

## Wiring <a id="section-7"></a>
Подключение идёт через `ClientConfig::providerCatalogRegistry`.

Доступ:
- `$client->providerCatalogs()`
- `$client->providerCatalog('pricing')`
- `$client->providerCatalogsForRequest(SomeRequest::class)`

Core intentionally не меняет `ClientInterface`; catalog access остаётся DX-методом `AbstractClient`.

## Граница v1 <a id="section-8"></a>
В первую итерацию входят:
- базовые catalog contracts
- read-only registry
- optional wiring через `ClientConfig`
- DX-accessors на уровне `AbstractClient`

Не входят:
- network sync / lazy loading
- billing semantics в core
- обязательная Laravel/container integration
- registrar/discovery слой для мультисервисных каталогов

Эти вещи можно добавлять отдельным follow-up, если появится реальная потребность.

## Пример provider-side реализации <a id="section-9"></a>
```php
use ApiSutra\Catalog\ProviderCatalogMeta;
use ApiSutra\Catalog\ProviderCatalogRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Catalog\ProviderCatalogInterface;
use ApiSutra\Contracts\Interfaces\Catalog\ProviderCatalogMetaInterface;
use DateTimeImmutable;

final readonly class PricingCatalog implements ProviderCatalogInterface
{
    public function key(): string
    {
        return 'pricing';
    }

    public function meta(): ProviderCatalogMetaInterface
    {
        return new ProviderCatalogMeta(
            generatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            source: 'provider-docs',
            sourceVersion: '2026-01-01',
        );
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    providerCatalogRegistry: new ProviderCatalogRegistry([
        new PricingCatalog(),
    ]),
);
```

## Когда нужен request-bound catalog <a id="section-10"></a>
Используйте `RequestBoundProviderCatalogInterface`, если каталог естественно
привязан к request layer:
- operation descriptors по request class
- capability matrix по конкретным методам
- request-specific schema catalog

Если каталог общий для всего SDK — достаточно `ProviderCatalogInterface`.

## Что это НЕ заменяет <a id="section-11"></a>
`OperationDescriptor` и provider catalogs — разные механизмы.

- `OperationDescriptor` — локальная metadata request-класса (`title`, `description`, `note`)
- provider catalog — отдельный агрегированный read-only слой SDK

Catalog не должен зависеть от `OperationDescriptor`, а `OperationDescriptor`
не должен рассматриваться как упрощённая форма catalog.

## Когда НЕ надо использовать catalog <a id="section-12"></a>
- envelope/meta конкретного HTTP-ответа
- continuation token
- pagination meta
- debug/audit/request trace

Для этого остаётся runtime result layer.

Для производного списка типов ответов используйте [ResponseDtoCatalog](response-dto-catalog.md).
