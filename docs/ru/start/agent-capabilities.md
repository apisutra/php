<!-- languages --> <a href="../../en/start/agent-capabilities.md">English</a> · <a href="agent-capabilities.md">Русский</a> <!-- /languages -->
# Карта возможностей для агента <a id="section-1"></a>

Ориентир для [агента — пользователя пакета](agent.md). Каждая строка связывает
потребность с подходящим механизмом и условием выбора. Подключать все механизмы
не требуется; полный контракт находится по ссылке в строке.

- [Устройство SDK](#section-2)
- [Подключение и конфигурация](#section-3)
- [Запросы и исходящие данные](#section-4)
- [DTO и гидратация](#section-5)
- [Политики исполнения](#section-6)
- [Несколько вызовов](#section-7)
- [Результат и диагностика](#section-8)
- [Расширения и проверка](#section-9)

## Устройство SDK <a id="section-2"></a>

| Потребность | Механизм и условие выбора |
| --- | --- |
| Организовать операции внешнего API | [Клиент и ресурсы](../reference/client/resources.md), [структура SDK](../guides/sdk/design.md). Типы операции принадлежат ей; общие модели выделяются при реальном переиспользовании. |
| Поддержать несколько сервисов или версий API | [Мультисервисный SDK](../guides/integration/multi-service.md), [версии](../reference/client/versioning.md). Разделение выбирается по сервисным контрактам и версиям API. |
| Сделать операции и типы доступными инструментам | [Инвентаризация операций](../reference/client/operation-inventory.md), [каталог DTO](../reference/client/response-dto-catalog.md), [справочники провайдера](../reference/client/catalogs.md). Статическое описание отделено от метаданных конкретного вызова. |

## Подключение и конфигурация <a id="section-3"></a>

| Потребность | Механизм и условие выбора |
| --- | --- |
| Создать клиент и настроить окружение | [ClientConfig](../reference/client/configuration.md), [сборка клиента](../reference/client/construction.md). Контейнер опционален; зависимости транспорта и auto-resolve учитываются отдельно. [Обзор клиента](../guides/client/showcase.md) показывает варианты подключения. |
| Переопределить отдельный вызов | [Опции запроса](../reference/request/declaration.md#section-14), [полный URL](../reference/request/declaration.md#section-16). Настройки одного исполнения отделены от общей конфигурации клиента. |
| Передавать credentials или работать с несколькими аккаунтами | [Auth-стратегии и scopes](../reference/auth/strategies.md), [хранение credentials](../reference/auth/credentials.md), [refresh токенов](../reference/auth/tokens.md). Стратегия выбирается по протоколу API; смена адреса учитывает OriginPolicy. |
| Получать и обновлять OAuth2-токены | [Client Credentials](../reference/auth/oauth2.md#client-credentials), [Authorization Code с PKCE S256 и state](../reference/auth/oauth2.md#authorization-code). [Credential lifecycle](../reference/auth/oauth2.md#credential) включает автоматический refresh, локальную координацию и callback сохранения; [снимки и scopes](../reference/auth/oauth2.md#storage) сохраняют состояние токенов/attempt. Redirect, хранение и [координация workers](../reference/auth/oauth2.md#workers) принадлежат приложению. |
| Реализовать управляемую token-аутентификацию | [ManagedTokenAuthenticatorInterface](../reference/auth/oauth2.md#managed-auth) привязывает состояние к области исполнения, строит refresh после получения владения и учитывает версии токенов. Custom auth также может реализовать только AuthenticatorInterface; одноразовые обмены требуют явных ограничений retry. |
| Использовать Laravel | [Подключение SDK](https://github.com/apisutra/laravel/blob/master/docs/ru/guides/integration/laravel.md), [интеграция своего SDK](https://github.com/apisutra/laravel/blob/master/docs/ru/guides/sdk/laravel.md). Providers, DI, конфигурация, валидация, RequestFactory и адаптер ответа относятся к интеграции; [standalone](../guides/integration/standalone.md) остаётся самостоятельным вариантом. |
| Выбрать язык сообщений | [Локализация](../reference/client/localization.md): английский по умолчанию, русский через `localization: 'ru'`, свой каталог через конфигурацию. Машинные коды ошибок не зависят от языка. |

## Запросы и исходящие данные <a id="section-4"></a>

| Потребность | Механизм и условие выбора |
| --- | --- |
| Описать операцию и тип ответа | [Декларация запроса](../reference/request/declaration.md), [HTTP-атрибуты](../reference/attributes/http.md), [Returns и unwrap](../reference/attributes/response.md). Метод, маршрут и путь данных берутся из контракта API. |
| Разместить данные в path, query, headers и body | [Части запроса](../reference/serialization/request-parts.md), [body и BodyRoot](../reference/serialization/body.md). Корневой список, объект и именованное поле — разные формы запроса. |
| Настроить формат коллекций, boolean или вложенного JSON | [Query и URI](../reference/serialization/uri-query.md), [форматы частей](../reference/serialization/request-parts.md), [JsonCast](../reference/serialization/casts.md). Query-массив и JSON-строка в одном поле требуют разных механизмов. |
| Проверить данные до отправки | [Валидация](../reference/client/validation.md), [полиморфный body](../reference/request/declaration.md). Для Validate нужен подключённый валидатор; гидратация ответа решает другую задачу. |
| Отделить удобное представление DTO от формата API | [DX и wire-сериализация](../reference/serialization/dto-output.md). Результат `toArray()` не обязательно совпадает с HTTP-payload. |
| Передать или получить файл | [Multipart, binary и Base64](../reference/files/uploads.md), [скачивание в файл/поток](../reference/files/downloads.md), [архивы](../reference/files/archives.md). Формат и способ хранения выбираются под API и объём данных; есть [исполняемый пример](../guides/recipes/files.md). |

## DTO и гидратация <a id="section-5"></a>

| Потребность | Механизм и условие выбора |
| --- | --- |
| Описать DTO с атрибутами или использовать plain-модели | [Модели](../reference/dto/models.md), [декларации](../reference/dto/declarations.md), [HydrationConfig](../reference/dto/configuration.md). Наследование базового DTO не обязательно; внешние правила позволяют описать модели без атрибутов. |
| Создать DTO через фабрики приложения | [Пользовательские гидраторы](../reference/dto/hydrators.md). Общий экземпляр или класс гидратора для отдельного запроса с DI; штатный путь для неподдерживаемых типов. |
| Преобразовать данные без HTTP | [Hydrator::forConfig()](../reference/dto/configuration.md), [вывод DTO](../reference/serialization/dto-output.md). DTO::from() не наследует настройки клиента; для них создайте гидратор с явной конфигурацией. |
| Сопоставить имена и пути полей | [From, To, Map](../reference/attributes/hydration.md), [FieldRule](../reference/dto/field-rules.md). Входное сопоставление и исходящее имя выбираются отдельно; пересечение деклараций имеет явные правила. |
| Отличать отсутствующее поле, null и default | [Defaults и providers](../reference/dto/defaults.md), [RequiredInput](../reference/dto/declarations.md), [ConstructorValue](../reference/dto/constructor-values.md). Обязательное присутствие, допустимость null и проверка значения конструктора — отдельные условия. |
| Контролировать типы, enum, даты и общую политику | [Скаляры](../reference/dto/scalars.md), [формы значений](../reference/dto/shapes.md), [профили](../reference/dto/profiles.md). Strict выбирается явно; преобразование не должно терять значимые данные API. |
| Разобрать вложенный объект, список или вариант ответа | [Коллекции и Nested](../reference/dto/collections.md), [варианты и discriminator](../reference/dto/variants.md), [формы](../reference/dto/shapes.md). Форма объекта, списка и словаря задаётся по реальному payload. |
| Сохранить неописанные входные данные | [Extras](../reference/dto/extras.md), [исходящий receiver](../reference/serialization/receiver-output.md). Receiver объявляется в модели явно; имя `_extra` само по себе ничего не включает. DX-вывод и исключение из запроса имеют разные контракты. |
| Выполнить нестандартное преобразование | [Casts и providers с контекстом](../reference/dto/scope.md). Вложенные преобразования через контекст сохраняют текущие правила; контекст нельзя использовать после возврата обработчика. |

[Обзор DTO](../guides/dto/showcase.md) собирает примеры в одной модели.
[Диагностика гидратации](../reference/dto/diagnostics.md) объясняет пути и границы ошибок.

## Политики исполнения <a id="section-6"></a>

| Потребность | Механизм и условие выбора |
| --- | --- |
| Повторить временно неудачный запрос | [Retry, backoff и идемпотентность](../reference/execution/retry.md). Допустимость повторов определяется эффектами операции; повтор изменяющего запроса требует отдельного обоснования. |
| Ограничить ожидание | [Таймауты и дедлайны](../reference/execution/deadlines.md). Таймаут одной попытки и бюджет всей операции решают разные задачи. |
| Соблюдать квоты API | [Rate-limit](../reference/execution/rate-limit.md), [общие лимиты через Redis](../reference/integrations/redis.md). Область лимита выбирается по квоте провайдера: операция, клиент или несколько процессов. |
| Учитывать серверный запрет после 429 | [Общий cooldown](../reference/execution/cooldown.md): автоматическая координация по классу/origin/credential по умолчанию внутри клиента; явный local/Redis backend связывает совпадающие области. Ожидание по бюджету и отказ при сбое хранилища; без очереди приложения. |
| Переиспользовать ответы и токены | [HTTP-кеш](../reference/execution/cache.md), [кеш auth и блокировки](../reference/auth/tokens.md). CacheConfig объединяет store и параметры; отключение HTTP-кеша не равнозначно отключению хранения auth. |

## Несколько вызовов <a id="section-7"></a>

| Потребность | Механизм и условие выбора |
| --- | --- |
| Обойти страницы API | [Пагинация](../reference/execution/pagination.md). Независимые page/offset поддерживают явную конкурентность с упорядоченным агрегатом и общим сроком; cursor и ленивый обход последовательны. Items-only без явного HydrationConfig остаётся raw; декларации DTO сами по себе не переключают этот путь. Для метаданных страницы подходит DTO-контейнер. |
| Потреблять элементы пагинации | [items()](../reference/execution/pagination-items.md). Лениво, последовательно, с сохранением DTO; FAILED бросает даже при throwOnErrors=false, PARTIAL отдаёт данные. |
| Запустить независимые HTTP-вызовы конкурентно | [sendAsync и Promise API](../reference/execution/transport.md#section-2). Штатный Guzzle совмещает HTTP и ожидания SDK без настройки цикла; `send()` остаётся синхронным. Стороннему транспорту нужна поддержка конкурентности, иначе async возвращает `configuration_error`. Синхронные callbacks приложения всё ещё могут блокировать цикл. |
| Выполнить набор запросов | [Конкурентные batch/pool, лимит конкурентности и стратегии ошибок](../reference/execution/batch-pool.md). У обоих builders есть копирующий `withConcurrency`; `withFailStrategy` задаёт обработку ошибок batch. Resolver pool должен возвращать int. Для независимых вызовов подходят parallel batch/pool, для строгого порядка — sequential batch. |
| Обработать большой или неизвестный вход | [Pool consume/consumeAsync](../reference/execution/pool-consumption.md) читает по свободным местам и возвращает счётчики. Данные и checkpoint сохраняет приложение; send хранит полную коллекцию. |
| Дождаться или отменить конкурентную работу | [Wait и отмена](../reference/execution/transport.md#section-2), [несколько ожидающих Promise](../reference/extensions/execution.md#section-5). Сохраняйте промисы и дожидайтесь завершения до выхода из job; это не fire-and-forget. Отмена освобождает ожидания и передачи SDK, но не отзывает уже отправленные данные. |
| Сочетать async-результаты с сохранением типов | [ResultPromiseInterface](../reference/results/promises.md) даёт после wait результат соответствующего sync-метода. Типизированные wait/then/otherwise разворачивают промисы SDK; Guzzle Utils::all объединяет их. Проверен PHPStan 2.2.13; подсказки PhpStorm и автоматический вывод DTO из dataOrFail не обещаются. |
| Независимо выбрать продолжение и выдачу ошибки | [FailStrategy](../reference/execution/batch-pool.md#section-14) определяет запуск остальных запросов; [throwOnErrors](../reference/results/errors.md#section-2) — выдачу окончательного агрегата. PARTIAL возвращается нормально; FAILED бросает исключение только при включённой настройке. |
| Связать зависимые операции | [Composite и DependsOn](../reference/request/composition.md). [sendInContextAsync](../reference/extensions/execution.md#section-3) запускает дочерний вызов с контекстом родителя. Composite и DependsOn используются при реальной зависимости результатов; [общий дедлайн](../reference/execution/deadlines.md) ограничивает весь сценарий. |
| Дождаться результата длительной операции | [Pending/Ready](../reference/execution/continuation-state.md), [await и polling](../reference/execution/continuation-await.md). [SkipContinuation](../reference/attributes/behavior.md#skip-continuation) исключает служебный запрос из mapping режимов клиента. Готовность определяется контрактом провайдера; наличие token или успешная гидратация не заменяют его. |

## Результат и диагностика <a id="section-8"></a>

| Потребность | Механизм и условие выбора |
| --- | --- |
| Получить данные или сведения об исполнении | [ResultHandle, ResolvedResult, ExecutionResult](../reference/results/handles.md). `send()` возвращает handle, `resolved()` — прикладной результат, `raw()` — сведения об исполнении. `dataOrFail()` извлекает данные или выбрасывает ошибку при FAILED. |
| Дать пользователю SDK свои методы результата | [Свой результат запроса и фабрика](../guides/recipes/custom-result.md). Поведение обёртки отделено от DTO ответа; тип для IDE требует явного описания публичного API SDK. |
| Представить ошибки внешнего API | [Ошибки и ErrorContext](../reference/results/errors.md). Ошибка, trace и служебные метаданные исполнения не становятся бизнес-полями DTO. Provider trace ID добавляется, если он существует в протоколе API. |
| Разобрать цепочку вызовов и причины сбоя | [Трассировка, audit, debug и логи](../reference/results/observability.md), [диагностика](diagnose.md). Sync- и async-исполнения имеют отдельные execution ID и сохраняют связи с родителями; отмена и освобождение незавершённой работы отражаются в терминальной диагностике. [Секретные поля отдельного запроса](../reference/auth/oauth2.md#execution) дополняют маскирование, не затрагивая другие типы запросов. Маскирование и передача trace-заголовков имеют отдельные правила. |

## Расширения и проверка <a id="section-9"></a>

| Потребность | Механизм и условие выбора |
| --- | --- |
| Дополнить поведение пакета | [Hooks](../reference/extensions/hooks.md), [расширения и response handlers](../reference/extensions/extensions.md), [свой транспорт](../reference/execution/transport.md). Выбирается узкая точка расширения; ненулевой результат response handler обходит штатные unwrap и гидратацию Returns. |
| Наблюдать или оборачивать каждое исполнение, включая вложенные запросы | [Декораторы исполнителя](../reference/extensions/execution.md#section-3). Подходят для поведения вокруг всей операции; изменения на отдельных стадиях относятся к хукам. Передавайте dispatcher на каждый вызов, чтобы child/auth/page/poll сохраняли внешний декоратор; override публичного send покрывает только прямые вызовы. |
| Создать собственную оркестрацию с каноническими результатами | [ClientExecutorInterface](../reference/extensions/execution.md#section-2) возвращает окончательный ExecutionResult, включая FAILED, независимо от throwOnErrors. [createScope()](../reference/extensions/execution.md#section-4) предоставляет часы/logger/trace клиента; владелец оркестрации управляет своим lifecycle и публичной выдачей. |
| Проверить SDK без внешнего сервиса | [Fake, последовательности и ассерты](../reference/testing/mocking.md), [record/playback](../reference/testing/fixtures.md), [примеры тестов](../guides/testing/unit.md). Проверяются форма запроса, преобразование данных и значимые ошибки изменённого сценария. |
| Сверить поведение с реальным API | [Live-проверки](../reference/testing/live.md), [анализ API](../guides/sdk/analysis.md), [покрытие SDK](../guides/sdk/coverage.md). Нужны явные условия запуска и разрешённые данные; зелёный fake не доказывает соответствие внешнему API. |

[Справочник](../reference/README.md) · [Атрибуты](../reference/attributes/README.md) ·
[Глоссарий](../glossary/README.md) · [Примеры](../examples/README.md).

## Инструменты и Laravel <a id="laravel-tools"></a>

[CLI](../reference/client/generation.md) · [Laravel fake, события, очередь, Artisan](https://github.com/apisutra/laravel/blob/master/docs/ru/README.md) · [Observer без I/O](../reference/results/observation.md).
