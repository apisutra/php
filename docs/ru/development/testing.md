<!-- languages --> <a href="../../en/development/testing.md">English</a> · <a href="testing.md">Русский</a> <!-- /languages -->
# Проверка SDK <a id="section-1"></a>

## Проверки самого пакета <a id="section-2"></a>

Команды выполняются из исходного checkout ApiSutra. Для потребителя пакета эти
инструменты не нужны; production-установка `composer install --no-dev` их исключает.

Ниже полный набор проверок, а не обязательная последовательность для каждой правки.
Начинайте с затронутых сценариев. При изменении общего поведения запускайте тесты
пакета; при изменении реализации или типов — также анализ и линтер по готовности.
Для текстовых правок достаточно проверки документации и пробельных ошибок;
изменённые PHP-примеры также требуют анализа и выполнения. Проверки установки архивов,
Redis и совместимости зависимостей/PHP нужны при изменении соответствующих областей,
в CI и при подготовке выпуска. Повторяйте успешные проверки только после относящихся
к ним изменений, сбоев или новых оснований для проверки.

```bash
composer install
composer validate --strict
composer dump-autoload --optimize --strict-psr
composer test -- --fail-on-deprecation --fail-on-warning
composer lint
composer analyse
composer check-docs
composer analyse-docs
composer check-package
```

`check-docs` и `check-package` требуют Python 3.9+. Первая команда проверяет локальные
пути Markdown-ссылок. Вторая сравнивает Git archive HEAD и Composer archive рабочей
копии, затем устанавливает каждый архив без dev-пакетов и запускает внешние smoke, включая PHP-пример быстрого старта из README.
Для ещё не закоммиченных изменений сначала добавьте предназначенные файлы в index
и вызовите `composer check-package -- --staged`. Отчёт можно сохранить через `--report`;
для обычных проверок отдельные файлы отчётов не нужны.

PHPStan проверяет весь src на уровне 5 с точным baseline существующих замечаний.
Новые ошибки и устаревшие записи baseline блокируют проверку. Это не утверждение
о полном отсутствии долга по типам. PSR-12 проверяется для src: ошибки блокируют
проверку, предупреждения о рекомендуемой длине строки остаются видимыми.

CI включает PHP 8.4/8.5, locked/lowest/latest зависимости, качество, архивы,
отдельный
[Docs CI](../../../.github/workflows/docs.yml) для документации и PHP-примеров.
Lowest определяется Composer с действующими
ограничениями совместимости и безопасности; это не установка заведомо уязвимых
исторических версий. Для воспроизведения в отдельном checkout:

```bash
composer update --prefer-lowest --prefer-stable --no-interaction
composer test
# Актуальный совместимый набор:
composer update --prefer-stable --no-interaction
composer test
```

Проверки Laravel находятся в [apisutra/laravel](https://github.com/apisutra/laravel/blob/master/docs/ru/development/testing.md).
Они используют отдельное приложение; для проверок ядра checkout адаптера не нужен.

На проверенном lowest-наборе старые Guzzle/PSR-7, PSR HTTP Factory, Symfony
Translation и deep-copy выдают deprecation под PHP 8.4/8.5. Это зафиксированное
ограничение старых зависимостей: их диагностика остаётся видимой в отдельном CI job.
Locked/latest проходят с `--fail-on-deprecation`; для lowest сохраняется вывод
`--display-deprecations`, без глобального подавления E_DEPRECATED. Обновление
совместимых зависимостей устраняет эти предупреждения. Runtime constraints не
сужены только ради исключения старых предупреждений из отчёта.

## Redis rate-limit <a id="section-3"></a>

Из корня исходного репозитория после `composer install`:

```bash
composer test:redis
```

Нужен работающий Docker с Compose (например, Docker Desktop; на Windows запускать
из WSL). Команда сама собирает PHP с phpredis, поднимает отдельный Redis, дожидается
healthcheck и запускает Redis-тесты. Локальный PHP не требует расширения redis.
При первом запуске загрузка образов и сборка могут занять несколько минут;
следующие запуски используют кеш сборки.

После успеха, ошибки или обычного прерывания Ctrl+C скрипт удаляет контейнеры,
тома и сеть своего запуска. Образ остаётся для повторного использования.
Каждый запуск получает отдельный Compose project; порты Redis не публикуются
на хост. Исходники подключены только для чтения. Код завершения тестов сохраняется;
ошибка очистки также завершает команду неуспешно.

Можно передавать параметры Pest:

```bash
composer test:redis -- --filter=NOSCRIPT
```

`composer test` сохраняет обычный запуск без Docker: Redis-сценарии пропускаются.
Локальный стенд использует PHP 8.4 / phpredis 6.2 / Redis 7.0; CI дополнительно
проверяет PHP 8.5 / phpredis 6.3 / Redis 8.2. В обязательном Redis job отсутствие
расширения или сервера вызывает ошибку, а не пропуск тестов.

Для ручного запуска при уже установленном phpredis и выделенном тестовом сервере:

```bash
APISUTRA_TEST_REDIS=1 APISUTRA_REDIS_HOST=127.0.0.1 APISUTRA_REDIS_PORT=6379 vendor/bin/pest tests/Integration/Redis
```

**Не направляйте ручные проверки на Redis приложения:** тесты меняют ACL/maxmemory
и очищают script cache. Команда `composer test:redis` создаёт собственный сервер
и не использует адрес Redis из окружения приложения.

Стенд находится в `tests/Integration/Redis/compose.yaml`, запуск и очистка — в
`tests/Support/test-redis.sh`. Проверяются отдельные workers с барьером, общая квота,
TTL, NOSCRIPT, ACL/OOM и потеря ответа после исполнения команды. Узкий тестовый
прокси нужен только для проверки неизвестного исхода; в SDK он не поставляется.

## Опубликованные примеры <a id="section-4"></a>

`composer analyse-docs` проверяет PHP-примеры отдельной конфигурацией PHPStan.
`composer check-docs` проверяет декларации и выполняет SDK, hydration-rules и continuation.
Laravel-часть тех же исходников анализирует и проверяет
`apisutra/laravel`. PHPStan ядра исключает только Laravel-каталог SDK. Для standalone
установки выполняйте `composer check-package` ядра, для Laravel — проверки адаптера.
Покрытие разделено по владельцам и не удалено.

При изменении Records SDK, используемого им API адаптера или их руководств проверяйте
оба актуальных checkout до слияния. Порядок — в [совместной проверке примера](https://github.com/apisutra/laravel/blob/master/docs/ru/development/testing.md#sdk-example).
Зелёный CI адаптера с закреплённой ревизией ядра не проверяет изменённый пример.
