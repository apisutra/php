<!-- languages --> <a href="../../../en/guides/recipes/extensions.md">English</a> · <a href="extensions.md">Русский</a> <!-- /languages -->
# Публичные расширения <a id="section-1"></a>

## Пример расширения <a id="section-2"></a>
```php
use ApiSutra\Contracts\Interfaces\Extensions\ExtensionInterface;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Enums\Hooks\HookPriority;
use ApiSutra\Extensions\ExtensionContext;

final class MetricsExtension implements ExtensionInterface
{
    public function getName(): string
    {
        return 'metrics';
    }

    public function register(ExtensionContext $context): void
    {
        $context->registerHook(Hook::AfterResponse, new MetricsHook(), HookPriority::Last);
        $context->registerResponseHandler('application/vnd.metrics+json', new MetricsResponseHandler());
    }

    public function boot(ClientConfig $config): void
    {
        // Инициализация после регистрации
    }

    public function checkDependencies(): void
    {
        // Проверка зависимостей
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
```
