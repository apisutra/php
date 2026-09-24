<?php

declare(strict_types=1);

namespace Example\Records\Laravel;

use ApiSutra\Auth\BearerAuthenticator;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Laravel\ClientConfigFactory as IntegrationConfigFactory;
use Example\Records\Config\ClientConfigFactory;
use Illuminate\Contracts\Foundation\Application;

final class LaravelClientConfigFactory
{
    public static function create(Application $app): ClientConfig
    {
        $values = $app['config']->get('apisutra.records');
        if (!is_array($values)) {
            throw new ConfigurationException('apisutra.records должен быть массивом настроек');
        }
        $baseUrl = $values['base_url'] ?? null;
        $timeout = $values['timeout'] ?? null;
        $token = $values['token'] ?? null;
        if (!is_string($baseUrl) || trim($baseUrl) === '') {
            throw new ConfigurationException('apisutra.records.base_url должен быть непустой строкой');
        }
        if (
            (!is_int($timeout) && !is_string($timeout))
            || ($timeout = filter_var($timeout, FILTER_VALIDATE_INT)) === false || $timeout < 0
        ) {
            throw new ConfigurationException('apisutra.records.timeout должен быть целым числом >= 0');
        }
        if ($token !== null && !is_string($token)) {
            throw new ConfigurationException('apisutra.records.token должен быть строкой или null');
        }

        // Общие правила SDK дополняются defaults переданного приложения без захвата контейнера.
        return $app->make(IntegrationConfigFactory::class)->make([
            ...ClientConfigFactory::defaults($baseUrl),
            'timeout' => $timeout,
            'auth' => $token === null || $token === '' ? null : new BearerAuthenticator($token),
        ]);
    }
}
