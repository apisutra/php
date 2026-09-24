<!-- languages --> <a href="../../en/development/request-serialization.md">English</a> · <a href="request-serialization.md">Русский</a> <!-- /languages -->
# Планы запроса и HTTP-границы <a id="section-1"></a>

[Serializer](../../../src/Serialization/Serializer.php) связывает преобразование значений
с HTTP-представлением. Декларации и настройки для автора SDK описаны в
[сериализации запросов](../reference/serialization/request-parts.md).

## Сборка зависимостей <a id="section-2"></a>

AbstractClient создаёт AttributeMetadataCache и Hydrator с HydrationConfig клиента.
Внутренний `Serializer::withDescriptions()` получает уже собранный RuleSetCompiler.
Его нейтральный MetadataCatalog используется также планами запроса и wire DTO;
внешние правила не компилируются повторно для каждого потребителя.

Standalone-конструкторы поддерживают аргументы, включая именованные вызовы.
Явная конфигурация проверяется при создании фасада. Внутренние потребители выхода
создаются при первом обращении; это не откладывает ошибки переданного набора правил.
Контейнер и HTTP-контекст для standalone не требуются. DX без конфигурации сохраняет
receiver как обычное поле, а wire обнаруживает и исключает атрибутный receiver.

## Размещение полей <a id="section-3"></a>

| Компонент | Решение |
| --- | --- |
| [RequestPartsPlanCompiler](../../../src/Serialization/Plan/RequestPartsPlanCompiler.php) | Читает HTTP-декларации из общего каталога, нормализует назначение полей и RequestDefaults, проверяет BodyRoot |
| [RequestPartsPlan](../../../src/Serialization/Plan/RequestPartsPlan.php) | Хранит поля, root и правило для unmapped; материализует объектные args на вызов |
| [RequestFieldPlan](../../../src/Serialization/Plan/RequestFieldPlan.php) | Содержит назначение, имена/форматы и SerializationValuePlan; учитывает плейсхолдер текущего URL |
| [RequestPartsCollector](../../../src/Serialization/RequestPartsCollector.php) | Читает значения с pagination overrides, исключает receiver, применяет текущую policy и собирает RequestPartsBag |
| [RequestUrlBuilder](../../../src/Serialization/RequestUrlBuilder.php) | Подставляет path и кодирует query |
| [FilePayloadPreparer](../../../src/Serialization/FilePayloadPreparer.php) | Формирует JSON, multipart, binary/base64 и сохраняет правила владения потоками |

План частей запроса отделён от входного DTO-плана. Кеш `:request-parts-plan` хранит
только декларации, enum/scalar параметры и Reflection-рецепты. Ни HTTP-метод,
ни URL, ни pagination overrides, ни значения запроса не фиксируются в нём.
Метод определяет Convention при каждом вызове; явный RequestDefaults имеет приоритет.

Ignore, static и непубличные поля пропускаются. Затем действуют BodyRoot → File →
Header → явный или неявный Path → Body → Query → unmapped. URL-плейсхолдер имеет
приоритет над Body/Query, но не меняет назначение File/Header. Конфликты BodyRoot
проверяются отдельно, включая плейсхолдеры конкретного URL и попадание в body по умолчанию.

Все объектные аргументы атрибутов материализуются до чтения значений, включая
пропущенные и null-поля. Ошибка аргумента не оставляет живые объекты в кеше.
Query/body используют общий [исполнитель значений](serialization.md); Header/Path
преобразуют enum, а File собирает FileInput. Эти части не вызывают Cast только из-за
его наличия на свойстве.

## Порядок HTTP-подготовки <a id="section-4"></a>

Serializer собирает части, затем применяет request enrichers и continuation mode
applicator. После этого строит URL, готовит payload и параметры передачи файлов.
Кеш HTTP, авторизация, retry, rate-limit и транспорт остаются в
[пайплайне](pipeline.md). Ошибка подготовки не приводит к отправке запроса;
она не отменяет уже выполненные эффекты пользовательских обработчиков.

## Входы ответа <a id="section-5"></a>

Returns, коллекции, пагинация, composite и финальная гидратация continuation
используют гидратор клиента. Повторный awaitAs преобразует сохранённый Ready-payload
тем же сервисом. Ошибка преобразования Ready остаётся ошибкой, не превращается в Pending.

`hydration: null` сохраняет raw items-only даже при атрибутах DTO. Явный HydrationConfig
включает типизацию элементов. HTTP-кеш хранит ответ: каждый клиент преобразует его
со своими правилами. Ненулевой результат response handler, RawResponse и Download
сохраняют отдельные ветки без обычной гидратации.

## Проверки <a id="section-6"></a>

[RequestPartsPlanTest](../../../tests/Unit/Serialization/RequestPartsPlanTest.php) проверяет
смену метода и URL при общем плане, приоритеты частей, ошибки, trace args/casts и
освобождение объектов. [AttributeHydrationEntriesTest](../../../tests/Unit/Serialization/AttributeHydrationEntriesTest.php)
и [ExternalHydrationEntriesTest](../../../tests/Unit/Serialization/ExternalHydrationEntriesTest.php)
покрывают входы ответа; тесты файлов, URL, enrichers и continuation проверяют адаптеры.
