<?php

declare(strict_types=1);

use Acme\Discovery\DiscoveryClient;
use Acme\Discovery\Requests\DiscoveryRequest;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Resolver\ClassMapProvider;
use ApiSutra\Resolver\ClientRegistry;
use ApiSutra\Resolver\ClientResolver;
use ApiSutra\Resolver\RequestNamespaceDetector;
use ApiSutra\Resolver\RequestScanner;
use ApiSutra\Resolver\ServiceRegistrar;
use ApiSutra\Tests\Stubs\Requests\PlainRequest;
use ApiSutra\Tests\Stubs\Resolver\NamespaceProviderClient;
use ApiSutra\Tests\Stubs\Resolver\MutableNamespaceClient;
describe('ServiceRegistrar', function () {
    it('регистрирует сервисы через auto-detect', function () {
        $client = new DiscoveryClient(
            new ClientConfig(baseUrl: 'https://example.test', environment: Environment::Local),
        );
        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $registrar = new ServiceRegistrar($registry, $detector);

        $registrar->register([$client]);

        $resolver = new ClientResolver($registry);
        $request = new DiscoveryRequest();

        expect($resolver->resolve($request))->toBe($client);
    });

    it('регистрирует сервисы через RequestNamespaceProviderInterface', function () {
        $client = new NamespaceProviderClient(
            new ClientConfig(baseUrl: 'https://example.test', environment: Environment::Local),
        );
        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $registrar = new ServiceRegistrar($registry, $detector);

        $registrar->register([$client]);

        $resolver = new ClientResolver($registry);
        $request = new PlainRequest('query');

        expect($resolver->resolve($request))->toBe($client);
    });
});

it('регистрирует тот же живой клиент независимо в двух реестрах', function (): void {
    $client = new MutableNamespaceClient(new ClientConfig(baseUrl: 'https://example.test'));
    $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
    $first = new ClientRegistry();
    $second = new ClientRegistry();
    (new ServiceRegistrar($first, $detector))->register([$client]);
    (new ServiceRegistrar($second, $detector))->register([$client]);
    expect($first->resolve('Example\\First\\Request'))->toBe($client)
        ->and($second->resolve('Example\\First\\Request'))->toBe($client);
});

it('повторяет регистрацию без дубликатов нормализованных namespace', function (): void {
    $client = new MutableNamespaceClient(new ClientConfig(baseUrl: 'https://example.test'));
    $client->namespaces = ['\\Example\\First\\', 'Example\\First'];
    $registry = new ClientRegistry();
    $registrar = new ServiceRegistrar($registry, new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider())));
    $registrar->register([$client]);
    $registrar->register([$client]);
    expect($registry->resolve('Example\\First\\Request'))->toBe($client);
});

it('позволяет повторить неудачное определение namespace', function (): void {
    $client = new MutableNamespaceClient(new ClientConfig(baseUrl: 'https://example.test'));
    $client->namespaces = [];
    $registry = new ClientRegistry();
    $registrar = new ServiceRegistrar($registry, new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider())));
    expect(fn () => $registrar->register([$client]))->toThrow(ConfigurationException::class);
    $client->namespaces = ['Example\\Recovered'];
    $registrar->register([$client]);
    expect($registry->resolve('Example\\Recovered\\Request'))->toBe($client);
});

it('после частичного конфликта завершает исправленную декларацию и сохраняет чужого владельца', function (): void {
    $client = new MutableNamespaceClient(new ClientConfig(baseUrl: 'https://example.test'));
    $owner = new MutableNamespaceClient(new ClientConfig(baseUrl: 'https://owner.test'));
    $client->namespaces = ['Example\\First', 'Example\\Conflict'];
    $registry = new ClientRegistry();
    $registry->register($owner, 'Example\\Conflict');
    $registrar = new ServiceRegistrar($registry, new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider())));
    expect(fn () => $registrar->register([$client]))->toThrow(ConfigurationException::class);
    expect(fn () => $registrar->register([$client]))->toThrow(ConfigurationException::class);
    expect($registry->resolve('Example\\First\\Request'))->toBe($client);
    $client->namespaces = ['Example\\First', 'Example\\Second'];
    $registrar->register([$client]);
    $registrar->register([$client]);
    expect($registry->resolve('Example\\Second\\Request'))->toBe($client)
        ->and($registry->resolve('Example\\Conflict\\Request'))->toBe($owner);
});
