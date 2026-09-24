<?php

declare(strict_types=1);

use Acme\Blank\EmptyDiscoveryClient;
use Acme\Discovery\DiscoveryClient;
use Acme\Fallback\FallbackClient;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Resolver\ClassMapProvider;
use ApiSutra\Resolver\RequestNamespaceDetector;
use ApiSutra\Resolver\RequestScanner;

describe('RequestNamespaceDetector', function () {
    it('находит namespace запросов клиента', function () {
        $client = new DiscoveryClient(
            new ClientConfig(baseUrl: 'https://example.test', environment: Environment::Local),
        );
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));

        $namespaces = $detector->detect($client);

        expect($namespaces)->toContain('Acme\\Discovery\\Requests');
    });

    it('возвращает несколько namespace при наличии разных запросов', function () {
        $client = new DiscoveryClient(
            new ClientConfig(baseUrl: 'https://example.test', environment: Environment::Local),
        );
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));

        $namespaces = $detector->detect($client);

        expect($namespaces)->toContain('Acme\\Discovery\\Requests');
        expect($namespaces)->toContain('Acme\\Discovery\\Resources');
    });

    it('использует fallback-конвенции при пустом root-scan', function () {
        $client = new FallbackClient(
            new ClientConfig(baseUrl: 'https://example.test', environment: Environment::Local),
        );
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));

        $namespaces = $detector->detect($client);

        expect($namespaces)->toContain('Acme\\Fallback\\Requests');
    });

    it('бросает исключение, если запросы не найдены', function () {
        $client = new EmptyDiscoveryClient(
            new ClientConfig(baseUrl: 'https://example.test', environment: Environment::Local),
        );
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));

        $detector->detect($client);
    })->throws(ConfigurationException::class);
});
