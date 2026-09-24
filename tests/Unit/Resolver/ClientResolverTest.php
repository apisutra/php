<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Request\RequestExecution;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Resolver\ClientRegistry;
use ApiSutra\Resolver\ClientResolver;
use ApiSutra\Result\ResolvedResultInterface;
use ApiSutra\Result\ResultHandle;
use ApiSutra\Response\ClientResponse;
use ApiSutra\Tests\Stubs\Requests\PlainRequest;
use ApiSutra\Tests\Stubs\Resolver\ResolverStubClientA;
use ApiSutra\Tests\Stubs\Resolver\ResolverStubClientB;

describe('ClientResolver', function () {
    it('разрешает клиента по запросу', function () {
        $registry = new ClientRegistry();
        $resolver = new ClientResolver($registry);
        $client = new ResolverStubClientA(new ClientConfig(baseUrl: 'https://example.test'));

        $registry->register($client, 'ApiSutra\\Tests\\Stubs\\Requests');

        $request = new PlainRequest('query');

        expect($resolver->resolve($request))->toBe($client);
    });

    it('разрешает клиента по execution-обертке', function () {
        $registry = new ClientRegistry();
        $resolver = new ClientResolver($registry);
        $client = new ResolverStubClientA(new ClientConfig(baseUrl: 'https://example.test'));

        $registry->register($client, 'ApiSutra\\Tests\\Stubs\\Requests');

        $request = new PlainRequest('query');
        $execution = new RequestExecution($request, RequestOptions::empty());

        expect($resolver->resolve($execution))->toBe($client);
    });

    it('бросает исключение при чужом владельце запроса', function () {
        $registry = new ClientRegistry();
        $resolver = new ClientResolver($registry);
        $clientA = new ResolverStubClientA(new ClientConfig(baseUrl: 'https://example.test'));
        $clientB = new ResolverStubClientB(new ClientConfig(baseUrl: 'https://example.test'));

        $registry->register($clientA, 'ApiSutra\\Tests\\Stubs\\Requests');

        $request = new PlainRequest('query');

        $resolver->assertOwnership($clientB, $request);
    })->throws(ConfigurationException::class);
});
