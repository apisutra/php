<!-- languages --> <a href="../../en/development/architecture.md">English</a> · <a href="architecture.md">Русский</a> <!-- /languages -->
# Архитектура ApiSutra <a id="section-1"></a>

Карта компонентов для разработчика пакета: кто принимает решения, как связаны
потоки выполнения и где подключаются расширения. Публичные параметры, приоритеты
и ограничения описаны в [справочнике](../reference/README.md).

## Цели SDK <a id="section-2"></a>

- декларативные запросы через атрибуты;
- единый пайплайн выполнения;
- расширяемость через публичные контракты;
- предсказуемые результаты и ошибки.

## Ключевые компоненты <a id="section-3"></a>

| Компонент | Ответственность |
| --- | --- |
| [ClientConfig](../../../src/Config/ClientConfig.php) | Настройки и зависимости конкретного клиента |
| [AbstractClient](../../../src/Core/AbstractClient.php) | Собирает сервисы и пайплайн, отправляет запросы, создаёт представления результата |
| [AbstractRequest](../../../src/Core/AbstractRequest.php) / [RequestExecution](../../../src/Request/RequestExecution.php) | Декларация операции и её обёртка с runtime-опциями |
| [RequestSpecResolver](../../../src/Request/RequestSpecResolver.php) | Читает атрибуты запроса и собирает RequestSpec |
| [ClientExecutor](../../../src/Execution/ClientExecutor.php) | Выбирает одиночное выполнение или обход страниц |
| [Pipeline](../../../src/Pipeline/Pipeline.php) | Организует отдельное выполнение и доставку его результата |
| [Serializer](../../../src/Serialization/Serializer.php) / [Hydrator](../../../src/Serialization/Hydrator.php) | Преобразуют запрос в HTTP-представление и данные ответа в DTO |
| [HydrationRules](../../../src/Serialization/Rules/HydrationRules.php) / [HydrationScope](../../../src/Serialization/Rules/HydrationScope.php) | Описывают внешние правила DTO и сохраняют их при вложенной гидратации |
| [ExecutionResult](../../../src/Result/ExecutionResult.php) / [ResultHandle](../../../src/Result/ResultHandle.php) | Хранят итог исполнения и предоставляют способы его получения |

`AbstractClient` собирает гидратор, сериализатор, реестры и кеш метаданных.
Реестры хуков, атрибутов и расширений можно передать в конструктор готовыми.
Один блок HydrationConfig задаёт политику и внешний набор. Клиент передаёт общий
RuleSetCompiler гидратору и сериализатору через внутреннюю сборку;
тот же гидратор используется для финального результата continuation.
Подробности сборки и клиентских входов — в [HTTP-границах](request-serialization.md).
Изоляция значений в кеше описана в [устройстве атрибутов](attributes.md#section-4).

## Локализация сообщений <a id="section-4"></a>

`Localization/Message` хранит ключ и параметры; `MessageFormatter` выбирает шаблон
по immutable `LocalizationConfig`. В `Localization/Resources/{en,ru}` находятся
каталоги по доменам. Глобально кешируются только неизменяемые каталоги, язык
исполнения находится в конфигурации клиента или standalone-компонента.

Метаданные хранят декларации сообщений, а границы клиента, результатов и
сериализаторов выбирают представление. `LocalizableExceptionTrait` создаёт копию
через специализированную фабрику, сохраняющую поля и исходное исключение в previous.
Добавляя тип исключения со своим конструктором, реализуйте `copyForLocalization()`.
Нельзя переводить строки стороннего владельца или добавлять параметры сообщения
в контекст логов в обход redaction. [Публичный контракт](../reference/client/localization.md).

## Принципы и границы <a id="section-5"></a>

- Транспорт подставляется через [TransportInterface](../reference/execution/transport.md).
- Контейнер опционален. Auto-resolve требует зарегистрированного резолвера,
  а встроенная валидация — фабрики, которую можно передать явно.
  См. [создание клиента](../reference/client/construction.md) и
  [валидацию](../reference/client/validation.md).
- Внутри пайплайна ошибки по умолчанию доставляются через результат;
  `throwOnErrors`, `dataOrFail()` и `await()` задают явные границы исключений.
  Подробнее — [доставка ошибок](error-handling.md).
- Конфиг и значения runtime-опций неизменяемы; рабочее состояние исполнения
  хранится в `PipelineContext`.
- Атрибуты описывают конфигурацию; особенности протокола внешнего API принадлежат SDK провайдера.

## Потоки выполнения <a id="section-6"></a>

### Граница Laravel <a id="section-7"></a>

`apisutra/php` содержит агностичные механизмы и необязательную standalone-валидацию.
`apisutra/laravel` содержит Laravel providers, defaults конфигурации, RequestFactory
и адаптер ответа. Он регистрирует default через ContainerProviderRegistry;
ядро не обнаруживает фреймворк. Явные providers сохраняют приоритет.
DefaultTransportFactory остаётся в ядре: ей нужен только общий контракт контейнера.
Каждый SDK поставляет собственный binding клиента и настройки протокола.

### Выполнение запросов <a id="section-8"></a>

Обычная отправка идёт через `AbstractClient` к `ClientExecutor`: одиночный запрос
попадает в `Pipeline`, а `Paginator` вызывает исполнителя для каждой страницы.
Batch и pool организуют несколько отправок через клиент. Внутри пайплайна
composite собирает результат дочерних запросов, а depends-on сначала выполняет
зависимости и затем основную операцию.

Continuation начинается при явном ожидании результата: отдельный сервис оценивает
готовность операции и при необходимости отправляет poll-запросы через клиент.
Это отдельный цикл протокола провайдера. Режим `sendAsync()` определяет интерфейс
возврата promise и сам по себе не гарантирует неблокирующий HTTP.

Выбор исполнителей, передача опций и дочернего контекста — в
[потоках выполнения](execution.md).

### Пайплайн (упрощённо) <a id="section-9"></a>

Для обычного запроса: контекст и бюджет → валидация → подготовка HTTP-запроса →
авторизация и хуки → кеш либо транспорт с retry/rate-limit → обработка ответа →
гидрация → результат.

Попадание в HTTP-кеш пропускает транспорт, но сохраняет обработку ответа.
Ошибка валидации завершает запрос до отправки; composite имеет собственную ветку
сборки результата. Точный порядок, точки расширения и ранние выходы описаны в
[пайплайне](pipeline.md).

## Резолв клиента <a id="section-10"></a>

`$client->send($request)` явно выбирает исполнителя. При `$request->send()`
[AbstractRequest](../../../src/Core/AbstractRequest.php) использует уже привязанный клиент
или получает `ClientResolverInterface` через `ContainerProviderRegistry`.

[ClientResolver](../../../src/Resolver/ClientResolver.php) разворачивает `RequestExecution`
до исходного запроса и обращается в [ClientRegistry](../../../src/Resolver/ClientRegistry.php).
Реестр выбирает самый длинный совпадающий namespace и кеширует найденный клиент
для класса запроса; новая регистрация сбрасывает этот кеш.

Auto-discovery заранее наполняет реестр. Оно не заменяет поиск клиента при отправке.
Если резолвер отсутствует, нужны явная отправка через клиент или `setClient()`;
если резолвер есть, но соответствие не найдено, возникает `ConfigurationException`.
При `setClient()` доступный резолвер также проверяет принадлежность классу клиента.

`ClientResolver` выбирает клиента, `ClientExecutor` — способ выполнения запроса,
`RequestSpecResolver` — его метаданные. Регистрация, discovery и настройка контейнера
описаны в [поиске клиента](../reference/client/discovery.md).
Поведение проверяют [ClientResolverTest](../../../tests/Unit/Resolver/ClientResolverTest.php)
и [ContainerProviderRequestResolverTest](../../../tests/Unit/Core/ContainerProviderRequestResolverTest.php).

## Точки расширения <a id="section-11"></a>

| Задача | Механизм и подробности |
| --- | --- |
| Обернуть корневое и вложенное исполнение | [ClientExecutorInterface и декораторы](../reference/extensions/execution.md) |
| Выполнить действие на границе отправки или гидратации | [Хуки](../reference/extensions/hooks.md), исполняемые HookRunner |
| Подключить несколько обработчиков одним модулем | [ExtensionInterface и реестры](../reference/extensions/extensions.md) |
| Обработать собственный формат ответа | [Response handler](../reference/extensions/extensions.md#section-7), выбираемый по MIME |
| Преобразовать значение поля | [Casts](../reference/serialization/casts.md); для вложенной гидратации — [HydrationContext](../reference/dto/scope.md) |
| Описать DTO без атрибутов | [Внешние правила полей](../reference/dto/field-rules.md) |
| Добавить собственную декларацию | [AttributeRegistry и обработчики атрибутов](attributes.md#section-6) |
| Подставить HTTP-клиент или стратегию авторизации | [Транспорт](../reference/execution/transport.md) и [авторизация](../reference/auth/strategies.md) |

Места вызова обработчиков — в [пайплайне](pipeline.md#section-7).
Публичные возможности расширения доступны автору SDK; внутренние сервисы ниже
служат для изменения самого ядра.

## Внутренний слой сериализации/гидрации <a id="section-12"></a>

- **PropertyTypeInspector** — чтение объявленных типов и проверка соответствия значений.
- **SerializationPlanCompiler / SerializationPlan** — выходной план полей из общего каталога;
  отделяет структуру и рецепты от текущей policy и значений. [Исполнение сериализации](serialization.md).
- **RequestPartsPlanCompiler / RequestPartsPlan** — размещение полей запроса и рецепты args;
  RequestPartsCollector исполняет план с текущими методом, URL и policy.
  [Планы запроса и HTTP-адаптеры](request-serialization.md).
- **SerializationValueResolver** — общий исполнитель value plan для `DtoSerializer` и query/body
  в `RequestPartsCollector`; конкретная ветвь выбирается по живому значению.
- **HydrationTypeSelector** — выбор ветки union/объявленного типа для гидрации.
- **BuiltinHydrationCaster** — выбор cast из атрибута/профиля и встроенных преобразований.
- **RuleSetCompiler** — общий resolver атрибутов и внешних declarations; проверяет конфликты, нормализует Shape в ValueShape, кеширует неизменяемые планы и лениво разрешает receiver runtime-класса.
- **MetadataCatalog** — общий структурный обход класса для гидрации, DTO-сериализации,
  частей запроса и реестра атрибутов; хранит невычисленные рецепты без policy и данных вызова.
- **HydrationPlanCompiler / HydrationFieldExecutor / HydrationObjectFactory** — входной
  план, стадии поля и отдельная фаза создания DTO. Args создаются для каждого узла,
  defaults конструктора — только при пропуске аргумента. [Исполнение гидратации](hydration.md).
- **HydrationScope** — путь, границы преобразований и состояние текущего вызова; не хранится в кеше описаний.
  [Контракт](../reference/dto/field-rules.md).
- **SafeScalarHydrationCaster** — безопасное приведение scalar-значений по типу DTO.

Общие механизмы преобразования следует развивать в этом слое, сохраняя согласованность
`Hydrator`, `DtoSerializer` и `RequestPartsCollector`. Публичные правила разделены на
[гидратацию DTO](../reference/dto/README.md) и
[исходящую сериализацию](../reference/serialization/README.md).

## Состояние преобразования и обработчики <a id="section-13"></a>

`TraversalState` хранит глубину и идентификаторы только активных предков.
HydrationScope считает DTO/collection узлы; DtoSerializer — активные объекты;
ReceiverOutput считает смешанные узлы локальным depth и передаёт активных предков
по ссылке, чтобы не копировать их на каждом уровне. EnumSerializationHelper сохраняет
скалярный счётчик отдельного сегмента массивов, который начинается заново после
перехода через DTO. Он использует общий предел без создания объекта на каждый массив.
Каждый успешный вход с состоянием закрывается через finally; соседние ссылки
не считаются циклом. DtoSerializer хранит отдельный frame TraversalState:
повторный вход в тот же фасад продолжает активную ветку. Между операциями frame
пуст и переиспользует ёмкость набора id; он не хранит DTO, policy, payload или контекст.
Завершение одного вызова не удаляет id другого незавершённого вызова фасада.
Stateless profile resolver/type inspector переиспользуются, результаты живых policy — нет.

HydrationContext/SerializationContext создаются лениво на вызов handler и закрываются
в finally. Закрытие удаляет scope, callback и extensions; сохранённый пользователем
контекст не удерживает payload и PipelineContext. HttpMappingAdapter создаёт снимок
HTTP-ссылок, направленные контексты не экспортируют изменяемый PipelineContext.
[Публичный контракт и lifetime](../reference/dto/scope.md).
