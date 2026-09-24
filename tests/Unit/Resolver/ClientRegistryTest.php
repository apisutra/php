<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Resolver\ClientRegistry;
use ApiSutra\Result\ResolvedResultInterface;
use ApiSutra\Result\ResultHandle;
use ApiSutra\Response\ClientResponse;
use ApiSutra\Tests\Stubs\Resolver\BaseStubClient;
use ApiSutra\Tests\Stubs\Resolver\StubClientA;
use ApiSutra\Tests\Stubs\Resolver\StubClientB;

describe('ClientRegistry', function () {
    it('регистрирует и разрешает клиента по namespace', function () {
        $registry = new ClientRegistry();
        $client = new StubClientA(new ClientConfig(baseUrl: 'https://example.test'));

        $registry->register($client, 'Acme\\Client');

        expect($registry->resolve('Acme\\Client\\Requests\\Foo'))->toBe($client);
    });

    it('использует самый длинный namespace при разрешении', function () {
        $registry = new ClientRegistry();
        $clientA = new StubClientA(new ClientConfig(baseUrl: 'https://example.test'));
        $clientB = new StubClientB(new ClientConfig(baseUrl: 'https://example.test'));

        $registry->register($clientA, 'Acme\\Client');
        $registry->register($clientB, 'Acme\\Client\\Billing');

        expect($registry->resolve('Acme\\Client\\Billing\\GetInvoice'))->toBe($clientB);
    });

    it('нормализует namespace при регистрации', function () {
        $registry = new ClientRegistry();
        $client = new StubClientA(new ClientConfig(baseUrl: 'https://example.test'));

        $registry->register($client, '\\Acme\\Client\\');

        expect($registry->resolve('Acme\\Client\\Requests\\Foo'))->toBe($client);
    });

    it('очищает resolved кеш при повторной регистрации', function () {
        $registry = new ClientRegistry();
        $clientA = new StubClientA(new ClientConfig(baseUrl: 'https://example.test'));
        $clientB = new StubClientB(new ClientConfig(baseUrl: 'https://example.test'));

        $registry->register($clientA, 'Acme\\Client');
        expect($registry->resolve('Acme\\Client\\Requests\\Foo'))->toBe($clientA);

        $registry->register($clientB, 'Acme\\Client', true);

        expect($registry->resolve('Acme\\Client\\Requests\\Foo'))->toBe($clientB);
    });

    it('бросает исключение при конфликте namespace', function () {
        $registry = new ClientRegistry();
        $clientA = new StubClientA(new ClientConfig(baseUrl: 'https://example.test'));
        $clientB = new StubClientB(new ClientConfig(baseUrl: 'https://example.test'));

        $registry->register($clientA, 'Acme\\Client');

        $registry->register($clientB, 'Acme\\Client');
    })->throws(ConfigurationException::class);

    it('бросает исключение при чужом владельце запроса', function () {
        $registry = new ClientRegistry();
        $clientA = new StubClientA(new ClientConfig(baseUrl: 'https://example.test'));
        $clientB = new StubClientB(new ClientConfig(baseUrl: 'https://example.test'));

        $registry->register($clientA, 'Acme\\Client');

        $registry->assertOwnership($clientB, 'Acme\\Client\\Requests\\Foo');
    })->throws(ConfigurationException::class);

    it('бросает исключение, если клиент не зарегистрирован', function () {
        $registry = new ClientRegistry();

        $registry->resolve('Acme\\Unknown\\Requests\\Foo');
    })->throws(ConfigurationException::class);
});
