<!-- languages --> <a href="../../en/start/use-sdk.md">English</a> · <a href="use-sdk.md">Русский</a> <!-- /languages -->
# Использовать готовый SDK <a id="section-1"></a>

Документация конкретного SDK определяет его клиент, ресурсы, credentials и
поддержанные операции. ApiSutra задаёт общий механизм выполнения и результата.

## Подключение <a id="section-2"></a>

1. Установите SDK и проверьте его требования к PHP, версии ApiSutra и транспорту.
2. Получите клиента по инструкции SDK. В Laravel готовый SDK поставляет свой provider
   и настройки; доступ через DI не требует копирования provider. Общие варианты:
   [standalone](../guides/integration/standalone.md) и [Laravel](https://github.com/apisutra/laravel/blob/master/docs/ru/guides/integration/laravel.md).
3. Передайте credentials через предусмотренную SDK конфигурацию. Не размещайте их
   в DTO ответа или фикстурах. При нескольких аккаунтах создавайте соответствующие
   конфигурации клиентов; правила изоляции описаны в [auth](../reference/auth/README.md).
4. Вызовите метод ресурса, заполните запрос и выберите получение результата.

[Обзор настройки клиента](../guides/client/showcase.md) показывает auth, timeout,
retry, квоты, кеш, DTO и разовые опции на одном исполняемом примере.

## Результат <a id="section-3"></a>

`dataOrFail()` удобен, когда неуспех должен стать исключением. `resolved()` даёт
данные, статус и ошибки для прикладного ветвления. `send()` возвращает `ResultHandle`;
его `raw()` предоставляет `ExecutionResult`, а не необработанную строку HTTP.
[Полный контракт результатов](../reference/results/handles.md).

Для запроса всех страниц используйте [пагинацию](../guides/recipes/pagination.md),
для фоновой задачи провайдера — [await](../guides/recipes/continuation.md).
Promise API и фоновая операция провайдера не означают одно и то же.

## Проверка интеграции <a id="section-4"></a>

Сначала воспроизведите один успешный и один ошибочный ответ через
[mock](../reference/testing/mocking.md). Проверьте auth scope, baseUrl, timeout и
обработку ошибок в своём приложении. Подключайте live-тесты отдельно.

При сбое следуйте [маршруту диагностики](diagnose.md).
