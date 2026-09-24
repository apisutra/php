<!-- languages --> <a href="../../../en/guides/sdk/first-operation.md">English</a> · <a href="first-operation.md">Русский</a> <!-- /languages -->
# Первая сквозная операция <a id="section-1"></a>

Начните с операции, для которой известны запрос, успешный ответ и ошибка.
В [учебном SDK](../../examples/sdk.md) это получение записи по id.

## Собрать путь от конфигурации до DTO <a id="section-2"></a>

1. [Фабрика конфигурации](../../../example/sdk/src/Config/ClientConfigFactory.php) задаёт
   baseUrl и правила DTO. Auth и протокольные defaults задаются на этом уровне.
2. [Клиент](../../../example/sdk/src/DemoClient.php) получает конфигурацию и транспорт явно;
   метод ресурса возвращает запрос, привязанный к этому клиенту.
3. [Запрос](../../../example/sdk/src/Resources/Records/Get/GetRecordRequest.php) задаёт
   method/path и размещение входных данных. Используйте [декларацию запроса](../../reference/request/declaration.md).
4. [DTO](../../../example/sdk/src/Resources/Records/Get/GetRecordResponseDto.php) описывает
   нужные данные. Выберите [атрибуты или внешний набор](../../start/describe-dto.md),
   затем задайте `Returns` и unwrap по подтверждённой форме ответа.
5. [Запуск](../../../example/sdk/run.php) демонстрирует success через dataOrFail() и
   failure через resolved(). Проверьте подготовленные параметры и оба результата.

## Ошибки и статусы <a id="section-3"></a>

Сопоставляйте коды провайдера с публичными кодами SDK централизованно через
[error mapper](../../reference/results/errors.md). Глобальные коды принадлежат
provider-wide слою, локальные — операции; одинаковые числа не делают их одним enum.
Для собственных статусов процесса выделите enum и функцию интерпретации в SDK.

`appCode` обычно остаётся null: универсальный SDK публикует `clientCode`, а приложение
сопоставляет его со своими кодами. Доменные приложения могут выбрать иной контракт явно.
`providerTraceId` добавляйте только при подтверждённой поддержке провайдером;
выдуманное значение ухудшает диагностику.

Технические поля envelope можно читать через
[ResultMetaExtractor](../../reference/results/handles.md#section-11).
Он не должен делать I/O, зависеть от debug или падать при отсутствии меты.
Собственный ResolvedResult полезен для типизированного доступа к такой meta;
его фабрика сохраняет общий контракт результата. Не копируйте технический envelope
в каждую бизнес-модель только ради прикладного доступа.

## Что подключать по мере необходимости <a id="section-4"></a>

| Условие API | Следующий шаг |
| --- | --- |
| Постраничные данные | [Пагинация](../recipes/pagination.md), itemsType и metadata resolver |
| Длительная операция и token | [Await](../recipes/continuation.md), явный критерий Ready/Pending |
| Несколько версий | [Версионирование](../../reference/client/versioning.md); explicit-версия без fallback |
| Данные о покрытии SDK | [Operation Inventory](../../reference/client/operation-inventory.md) |
| Статические справочники, capabilities, цены | [Provider catalogs](../../reference/client/catalogs.md) |
| Список типов ответов SDK | [ResponseDtoCatalog](../../reference/client/response-dto-catalog.md) |

Эти механизмы не являются условиями для первой обычной операции. Выбор следует
из фактов API и задачи потребителя.

## Принять результат <a id="section-5"></a>

Пройдите [проверку операции](../testing/unit.md). Полученный SDK должен запускаться
без скрытых переменных и случайного HTTP в тестах. Зафиксируйте ограничения и
перейдите к [покрытию](coverage.md).
