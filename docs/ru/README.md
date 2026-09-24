<!-- languages --> <a href="../en/README.md">English</a> · <a href="README.md">Русский</a> <!-- /languages -->
# Документация ApiSutra <a id="section-1"></a>

[Собственные DTO: атрибуты и общая политика](reference/dto/declarations.md).

Руководства и справочник предназначены для пользователя пакета: автора SDK
внешнего API и разработчика приложения, которое использует такой SDK.
Разработка самой ApiSutra выделена в отдельный раздел ниже.

- [OAuth2: готовые grants, PKCE, refresh и хранение](reference/auth/oauth2.md).

## Начать с задачи <a id="section-2"></a>

- [Первый запуск](guides/quickstart.md) — исполняемый пример без сети.
- [Создать SDK](start/create-sdk.md).
- [Добавить операцию](start/add-operation.md).
- [Описать DTO](start/describe-dto.md).
- [Изучить возможности DTO на одном примере](guides/dto/showcase.md).
- [Изучить создание и настройку клиента](guides/client/showcase.md).
- [Добавить свои методы к результату SDK](guides/recipes/custom-result.md).
- [Загрузить, скачать файл и открыть архив](guides/recipes/files.md) — запускаемый пример без сети.
- [Использовать готовый SDK](start/use-sdk.md).
- [Типизированные async-результаты и цепочки](reference/results/promises.md).
- [Выполнять HTTP-вызовы конкурентно через sendAsync](reference/execution/transport.md#section-2) · [Ограничить конкурентность batch/pool](reference/execution/batch-pool.md).
- [Обработать большой pool постепенно без хранения результатов](reference/execution/pool-consumption.md).
- [Подключить готовый SDK в Laravel](https://github.com/apisutra/laravel/blob/master/docs/ru/guides/integration/laravel.md) · [Добавить Laravel-поддержку в свой SDK](https://github.com/apisutra/laravel/blob/master/docs/ru/guides/sdk/laravel.md).
- [Настроить язык сообщений](reference/client/localization.md).
- [Диагностировать проблему](start/diagnose.md).

[Точки входа](start/README.md) помогают выбрать разбор задачи.
Для ИИ-агента: [использование пакета](start/agent.md) и
[карта возможностей по задачам](start/agent-capabilities.md).

## Найти подробности <a id="section-3"></a>

| Раздел | Для чего он нужен |
| --- | --- |
| [Руководства](guides/README.md) | Выполнить задачу с примером и проверить результат |
| [Справочник](reference/README.md) | Узнать точные правила API, приоритеты и ограничения |
| [Словарь](glossary/README.md) | Понять термин и перейти к его контракту |
| [Примеры](examples/README.md) | Запустить опубликованный код и изучить раскладку SDK |

## Разрабатывать ApiSutra <a id="section-4"></a>

[Руководство разработчика](https://github.com/apisutra/php/blob/master/docs/ru/development/README.md)
ведёт к подготовке checkout, архитектуре, исходникам и проверкам пакета.
Для ИИ-агента — [правила разработки ApiSutra](https://github.com/apisutra/php/blob/master/.agents/README.md).
Этот раздел предназначен для изменения самой ApiSutra. Подключение поддержанных
casts, hooks и extensions относится к пользовательским маршрутам выше.
