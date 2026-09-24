<!-- languages --> <a href="../../en/examples/README.md">English</a> · <a href="README.md">Русский</a> <!-- /languages -->
# Исполняемые примеры <a id="section-1"></a>

Примеры входят в поставку пакета. Для запуска нужен Composer autoload; локальные
фикстуры позволяют обойтись без сети и credentials.

| Пример | Что показывает |
| --- | --- |
| [Async-результаты](../../example/async-results/run.php) | Типизированные промисы single/batch/pool/consume, вложенный then, FAILED и reject, otherwise — без сети |
| [Фабрики DTO и ленивые items](../../example/dto-hydrator/run.php) | Private-конструктор, вложенные DTO, контекст гидратации, явная сериализация и ошибка страницы без потери данных |
| [OAuth2](../../example/oauth2/run.php) | Переиспользование Client Credentials, async-обмен code, неверный callback, refresh после 401 и сохранение ротации — без сети |
| [Исключения операций](result-errors.md) | Проверка Returns, сообщения операций, одна фабрика исключений и fallback |
| [Язык сообщений](localization.md) | Два независимых клиента, ошибка DTO, каталог SDK и буквальные строки |
| [Records SDK](sdk.md) | Конфигурация, транспорт, ресурс, запрос, DTO и ошибка HTTP |
| [Возможности клиента](client-showcase.md) | Auth, таймауты, retry, квоты, кеш, DTO, диагностика и разовые опции |
| [Собственный результат](custom-result.md) | Методы SDK поверх ResolvedResultInterface, фабрика и сохранение стандартных ошибок |
| [Возможности DTO](dto-showcase.md) | Атрибуты и правила одного товара, сериализация, Base64-поле, defaults и диагностика |
| [Файлы и архивы](files.md) | Multipart/binary/Base64 upload, download в путь и поток, чтение TAR |
| [Ожидание операции](continuation.md) | Pending/Ready, token, строгий финал и кеш await |
| [Правила DTO](hydration-rules.md) | Mapping, nested/each, strict-список, extras и scoped cast |

[Quickstart](../guides/quickstart.md) запускает Records SDK. Тот же SDK можно установить
через Composer и [подключить в Laravel](https://github.com/apisutra/laravel/blob/master/docs/ru/guides/integration/laravel.md).
Все контракты находятся в [справочнике](../reference/README.md).

Фрагменты справочников объясняют отдельный API-вызов и могут требовать уже созданного
клиента или модели. Полный воспроизводимый код начинается с README соответствующего
примера; классы лежат в отдельных файлах.
