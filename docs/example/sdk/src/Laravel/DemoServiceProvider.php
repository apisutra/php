<?php

declare(strict_types=1);

namespace Example\Records\Laravel;

use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Laravel\SdkServiceProvider;
use ApiSutra\Resolver\ServiceRegistrar;
use Example\Records\DemoClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

final class DemoServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2) . '/config/records.php', 'apisutra.records');
        $this->app->singletonIf(DemoClient::class, static function (Application $app): DemoClient {
            self::requireIntegration();
            return new DemoClient(
                LaravelClientConfigFactory::create($app),
                $app->make(TransportInterface::class),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__, 2) . '/config/records.php' => $this->app->configPath('apisutra/records.php'),
        ], 'records-config');

        $marker = self::class . '.booted';
        if ($this->app->bound($marker)) {
            return;
        }
        $this->app->instance($marker, true);

        // Регистрация нужна и для экземпляра, созданного пользовательским binding.
        if (class_exists(SdkServiceProvider::class)) {
            $this->callAfterResolving(DemoClient::class, static function (DemoClient $client, Application $app): void {
                $app->make(ServiceRegistrar::class)->register([$client]);
            });
        }

        // Общий resolving выполняется до типизированного hook AbstractRequest в ядре.
        $this->app->resolving(static function (mixed $object, Application $app): void {
            if (
                $object instanceof AbstractRequest && !$object->hasClient()
                && str_starts_with($object::class, DemoClient::REQUEST_NAMESPACE . '\\')
            ) {
                self::requireIntegration();
                $app->make(ServiceRegistrar::class)->register([$app->make(DemoClient::class)]);
            }
        });
    }

    private static function requireIntegration(): void
    {
        if (!class_exists(SdkServiceProvider::class)) {
            throw new ConfigurationException(
                'Для DI Records SDK установите адаптер: composer require apisutra/laravel. '
                . 'Без адаптера создайте DemoClient явно, как в standalone-примере.',
            );
        }
    }
}
