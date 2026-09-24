<!-- languages --> <a href="../../../en/guides/sdk/coverage.md">English</a> · <a href="coverage.md">Русский</a> <!-- /languages -->
# Покрытие API и критерии SDK <a id="section-1"></a>

Ведите карту операций: публичный вызов SDK, endpoint/версия, DTO, готовность тестов,
ограничения и источник подтверждения. Для снимка реализованного API используйте
[Operation Inventory](../../reference/client/operation-inventory.md); он не знает
ещё не реализованных операций внешнего API, поэтому не заменяет карту плана.

## Проверить архитектуру и модели <a id="section-2"></a>

- [] Клиент, конфигурация и транспорт собираются воспроизводимо; пример исполняется.
- [] [Владение типами](design.md) соответствует ресурсам и операциям; Domain содержит
  только действительно общие понятия, большие модели сгруппированы по смыслу.
- [] Общие базы введены по необходимости; plain DTO с внешними правилами не требуют
  наследования от ApiSutra. В атрибутной модели общие профили привязаны явно.
- [] Повторяющиеся payload вынесены в DTO, входные и выходные имена определены
  отдельно. Enum и представление title выбраны по реальному пользовательскому контракту.

## Проверить DTO и исходящий payload <a id="section-3"></a>

- [] Типы, [missing/null/default](../../reference/dto/defaults.md),
  [mapping](../../reference/dto/profiles.md) и [вложенность](../../reference/dto/shapes.md)
  подтверждены фикстурами; strict не включён вслепую для числовых строк.
- [] Элементы scalar-списка проверяются явной формой или itemCast, если это требуется;
  одного PHPDoc недостаточно. required не подменяется пустой typed collection.
- [] [RequestOneOf/Discriminator](../../reference/request/declaration.md) и custom
  preflight покрыты при наличии взаимоисключающих полей или сложной проверки входа.
- [] [DX и wire](../../reference/serialization/dto-output.md) различаются осознанно:
  enum, даты, naming, query arrays, текстовые boolean и JSON-строки имеют нужный формат.
- [] [Receiver](../../reference/serialization/receiver-output.md) проверен на входе,
  при ручном создании DTO и отправке; ограничения casts/opaque-обёрток учтены.
- [] Пользовательская вложенная гидратация сохраняет [scope](../../reference/dto/scope.md).

## Проверить протокол и окружение <a id="section-4"></a>

- [] Credentials размещаются через [auth/enrichment](../../reference/auth/README.md),
  scopes и secretKeys заданы централизованно; исключения отдельных endpoints явные.
- [] [Квоты](../../reference/execution/rate-limit.md), [retry](../../reference/execution/retry.md),
  кеш и timeouts соответствуют фактам API. Retry разрешён только для безопасного повтора.
- [] При использовании [зависимостей и composite](../../reference/request/composition.md)
  проверены роли, ошибки и общий бюджет; hooks не нарушают формат подготовленного запроса.
- [] [Файлы](../../reference/files/README.md) проверены отдельно от JSON, включая
  владение потоками, download destination и необходимые драйверы архивов.
- [] Production/sandbox разделены конфигурацией. [Мультисервисность](../integration/multi-service.md)
  и [версии](../../reference/client/versioning.md) вводятся при реальном различии контрактов.
- [] [Laravel](https://github.com/apisutra/laravel/blob/master/docs/ru/guides/integration/laravel.md), если он поддержан SDK, имеет проверенный
  binding, config и переход со старых ключей при наличии существующих потребителей.

## Проверить результаты и диагностику <a id="section-5"></a>

- [] Глобальные и локальные коды ошибок разделены; mapper выдаёт документированные
  clientCode/status. appCode и providerTraceId вводятся только при подходящем контракте.
- [] [Runtime meta](../../reference/results/handles.md#section-11)
  отделена от статических [каталогов](../../reference/client/catalogs.md).
- [] [Пагинация](../../reference/execution/pagination.md) покрывает metadata, items,
  контейнер/коллекцию и защитную остановку, если операция постраничная.
- [] [Continuation](../../reference/execution/continuation-state.md) покрывает явную
  готовность, token, строгий финал, лимит и ошибку; получение token не заменяет await.
- [] [Логи](../../reference/results/observability.md) содержат достаточную безопасную
  диагностику; исходные секреты и значения не попадают в автоматический экспорт.

## Проверить тесты и выпуск <a id="section-6"></a>

Минимум — [запрос, DTO и ошибки](../testing/unit.md) на локальных фикстурах.
Record/playback полезен для дорогих, медленных, лимитированных или нестабильных API.
При oneOf используйте поддержанный RequestContractTestHelper. Пагинация, await,
files, batch и Laravel получают отдельные проверки при фактическом использовании.

[Live-контур](../testing/live.md) включается по необходимости: gating, credentials,
стоимость и изменяемое состояние внешней системы должны быть явными. LiveTestGuard,
LiveClientFactory, fixture loader, cache, dumping и Makefile — код SDK, если вы его
добавили; ядро предоставляет только перечисленные в справочнике helpers.

Финальная проверка — [документация и выпуск](release.md). Для неподдержанной операции
или неизвестного условия записывайте статус и причину, не отмечайте её выполненной.
