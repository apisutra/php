<!-- languages --> <a href="../../../en/reference/extensions/extensions.md">English</a> · <a href="extensions.md">Русский</a> <!-- /languages -->
# Публичные расширения <a id="section-1"></a>

Расширения позволяют подключать обработчики и дополнительное поведение без
изменения ядра клиента.

## Для каких задач <a id="section-2"></a>
- регистрация кастов, хуков и response-handlers
- добавление своих обработчиков атрибутов
- централизованное подключение кросс-срезов (логирование, метрики, трассировка)

Если кастомизаций не нужно — `extensions` можно не задавать, поведение по умолчанию
не изменится.

Пример регистрации — в [руководстве по расширениям](../../guides/recipes/extensions.md).

## Рекомендуемый вариант: отдельный класс <a id="section-3"></a>
Импорты опущены — ниже показан каркас и назначение методов.
```php
final class ExampleExtension implements ExtensionInterface
{
    public function getName(): string
    {
        // Уникальный ключ расширения: регистрация, конфликты, логирование.
        return 'example';
    }

    public function register(ExtensionContext $context): void
    {
        // Регистрация кастов, хуков, обработчиков ответов и атрибутов.
        // Пример: $context->registerHook(...); $context->registerCast(...);
    }

    public function boot(ClientConfig $config): void
    {
        // Инициализация после регистрации (кеши, клиенты, подготовка ресурсов).
    }

    public function checkDependencies(): void
    {
        // Проверка зависимостей/конфигурации, при проблеме — исключение.
    }

    public function isEnabled(): bool
    {
        // Возвращает, активно ли расширение в текущих условиях.
        return true;
    }
}
```

## Базовая настройка <a id="section-4"></a>
```php
$config = new ClientConfig(
    baseUrl: 'https://api.example',
    extensions: [
        new ExampleExtension(),
    ],
);
```

Extensions — это единый механизм расширения SDK: касты, хуки, обработчики ответов
и кастомные атрибуты.

## Жизненный цикл <a id="section-5"></a>

При подключении расширения вызываются `checkDependencies()` и `register()`.
Зарегистрированные хуки, касты и обработчики атрибутов становятся доступны через
соответствующие реестры.

При выборе response handler проверяется `isEnabled()` его расширения. Если оно
отключено, возникает `ExtensionDisabledException`. Перед первым использованием
выбранного обработчика вызывается `boot()`; успешный boot выполняется один раз
за время регистрации расширения. Для расширения только с хуками или кастами
этот путь не запускается: `boot()` не является общей фазой сборки клиента,
а `isEnabled()` не отключает регистрации в других реестрах.

## Что можно регистрировать <a id="section-6"></a>
- касты сериализации запросов (`registerCast`); участие источников в гидратации
  описано в [справке casts](../serialization/casts.md#section-9)
- хуки (`registerHook`)
- обработчики ответов (`registerResponseHandler`)
- обработчики атрибутов (`registerAttributeHandler`)

## Response handlers и приоритет <a id="section-7"></a>
Поиск обработчика происходит по `Content-Type` с приоритетом:
1) точное совпадение (`application/json`)
2) тип‑маска (`application/*`)
3) `*`

Если MIME не совпал, используется `supports()` обработчика.

Ненулевой результат `handle()` становится значением ответа и обходит штатные
`Returns::unwrap` и гидрацию DTO. Возврат null передаёт обработку стандартному пути.
Последующие хуки `AfterHydrate` сохраняются. Для изменения данных перед штатной
гидрацией используйте [BeforeHydrate](hooks.md#section-6).

## Конфликты и override <a id="section-8"></a>
Если для MIME уже есть обработчик:
- без `override` → `ExtensionConflictException`
- с `override = true` — обработчик будет заменён

По умолчанию обработчики появляются только через подключённые extensions.
`override = true` используйте, когда хотите заменить поведение
другого extension для того же `Content-Type`.
