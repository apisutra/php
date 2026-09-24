<!-- languages --> <a href="../../../en/reference/client/localization.md">English</a> · <a href="localization.md">Русский</a> <!-- /languages -->
# Язык сообщений <a id="section-1"></a>

ApiSutra выдаёт собственные сообщения на английском по умолчанию. Встроены `en` и
`ru`; дополнительных зависимостей не требуется. Настройка принадлежит экземпляру
клиента и сохраняется при `ClientConfig::with()`.

Фрагмент конфигурации для вашего SDK:

```php
use ApiSutra\Config\ClientConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example.test',
    localization: 'ru',
);
$english = $config->with(localization: 'en');
```

Строка — сокращение для `new LocalizationConfig('ru')`. Конструктор и `with()` принимают `LocalizationConfig|string`; свойство `$config->localization`
всегда содержит `LocalizationConfig`. Объект нужен для собственных каталогов
и переопределений. `with(localization: 'en')` заменяет блок целиком, включая каталоги;
поля без override сохраняются.

Создайте второй клиент с `$english`: уже созданный русский клиент не изменится.
Язык действует на исключения ApiSutra, `RequestError`, `ClientError`, сообщения
результата и собственные записи логов. Он сохраняется при async, batch/pool,
пагинации, вложенной гидратации и `awaitAs()`, включая повторное преобразование
сохранённого результата. Общий кеш метаданных не хранит язык.

[Исполняемый пример](../../examples/localization.md) показывает два языка,
ошибку DTO и дополнительный каталог SDK.

## Что остаётся исходным <a id="section-2"></a>

- `ErrorCode`, `reason`, `path`, `sourcePath`, статусы и технические ключи контекста.
- Тексты ответа внешнего API, сообщения стороннего валидатора и исключения с
  обычной строкой из пользовательского обработчика.
- HTTP-заголовки, payload, ключи кеша и данные DTO. `Accept-Language` задавайте
  отдельно, если этого требует провайдер.

`ErrorCode::title($localization)` принимает тот же блок; без аргумента возвращает
английское название. Локализация не заменяет [маппинг ошибок](../results/errors.md).

## Каталог SDK и переопределения <a id="section-3"></a>

`Message` содержит стабильный ключ и именованные параметры. В `messages` передайте
массив «язык → ключ → шаблон». Используйте собственный префикс для сообщений SDK.

```php
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;

$localization = new LocalizationConfig('ru', messages: [
    'en' => ['records.limit' => 'Limit exceeded: {limit}'],
    'ru' => ['records.limit' => 'Превышен лимит: {limit}'],
]);
$error = new ConfigurationException(new Message('records.limit', ['limit' => 10]));
$translated = $error->localized($localization);
// $translated->getMessage(): «Превышен лимит: 10».
```

Если обработчик бросит такое исключение при выполнении запроса, клиент применит
свой каталог автоматически. Вне клиента передайте `localization:` в конструктор
`SdkException`/`ConfigurationException` или вызовите `localized()` явно.
Встроенные ключи и шаблоны доступны через `MessageCatalog::all('en')` и `all('ru')`.

Допустимы scalar, null и вложенный `Message`; произвольные объекты запрещены.
Подстановка `{name}` не выполняет PHP и не интерпретирует плейсхолдеры внутри
подставленного значения. Дескриптор доступен через `messageDefinition()`, но не
добавляется автоматически в JSON ошибки или лог. Не передавайте секреты в текст
ошибки; правила [redaction](../results/observability.md) продолжают действовать.

Locale нормализуется: `ru_RU` → `ru-ru`. Порядок поиска: выбранный вариант,
базовый язык, `en`; в каждом языке каталог пользователя предшествует встроенному.
Неизвестный ключ возвращается как текст. Набор placeholders переопределения должен
совпадать с английским шаблоном; некорректный перевод пропускается. Без английского
шаблона SDK эталон — имена переданных параметров. При отсутствии обязательного
параметра возвращается ключ. Неверная структура каталога или locale вызывает
`ConfigurationException`; пустой ключ/неподдержанный параметр — `InvalidArgumentException`.

## Standalone и Laravel <a id="section-4"></a>

`Hydrator`, `Serializer`, `DtoSerializer`, `ClientRegistry`, `ServiceRegistrar` и
`RequestNamespaceDetector` принимают необязательный `localization:` в конце
конструктора. `Hydrator::forConfig($hydration, $localization)` также сохраняет блок.
Без явной настройки standalone работает на английском; `Dto::from()`, `toArray()`
и `default()` не заимствуют язык последнего клиента.

С установленным `apisutra/laravel` передавайте выбранный язык в фабрике клиента:
`app(\ApiSutra\Laravel\ClientConfigFactory::class)->make(['baseUrl' => $url, 'localization' => 'ru'])`.
В config-файле приложения можно хранить строку locale; для собственного каталога
соберите `LocalizationConfig` в фабрике.
Ядро не читает `app.locale`: приложение само решает, откуда брать язык.
Общему регистратору язык задаётся отдельно; клиент не меняет общий реестр.

Ошибки при создании `ClientConfig` используют переданный блок. Ошибки объектов,
созданных **до** него (например, `new RetryConfig(...)`), ещё не имеют контекста
клиента и по умолчанию английские. Для них доступен явный `localized()`.

## Исключения и повторное представление <a id="section-5"></a>

`getMessage()` возвращает строку. Если перевод меняет текст,
`localized()` создаёт исключение того же SDK-класса, сохраняет его поля и кладёт
оригинал в `previous`, вместе с первоначальным stack trace. Исходный объект не
меняется. Если текст тот же или исходное сообщение — обычная строка, возвращается
тот же объект. Цепочка `previous` поэтому может стать длиннее.

`ExecutionResult::localized($localization)` создаёт представление ошибок и вложенных
результатов с другим языком; данные, ответ и исходный результат сохраняются.
`localization()` возвращает настройки представления. Пользовательские строковые
сообщения при этом остаются как есть.

Для подкласса `SdkException` с собственной сигнатурой конструктора и `Message`
переопределите `protected copyForLocalization(): static`: создайте тот же тип,
перенесите собственные поля и передайте `$this` как `previous`. Для обычной строки
фабрика не вызывается. У подклассов `ExecutionResult`, использующих `localized()`,
сохраняйте сигнатуру конструктора базового результата либо переопределяйте метод.

[Параметры клиента](configuration.md).

## Локализация диагностики <a id="section-6"></a>

Перевод сообщения сохраняет `ExecutionTrace`, audit и связь parentExecutionId.
Машинный `event` и поля корреляции логов не зависят от EN/RU. Ошибка внешнего
logger или форматирования диагностической записи не меняет результат API.
[Поля логов и audit](../results/observability.md).
