<?php

declare(strict_types=1);

use ApiSutra\Auth\BearerAuthenticator;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\Core\ClientResolverInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use ApiSutra\Execution\Async\AsyncTask;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Resolver\ClientRegistry;
use ApiSutra\Resolver\ClientResolver;
use ApiSutra\Support\ContainerProviderRegistry;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Auth\ClientAwareRefreshRequest;
use ApiSutra\Tests\Stubs\Auth\OAuth2\ResourceRequest;
use ApiSutra\Tests\Stubs\Support\MapContainerProvider;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Http\PreparedRequest;
use GuzzleHttp\Promise\Utils;

it('исполняет refresh в выбранном клиенте независимо от обёртки и registry', function (bool $wrapped, bool $explicitAuth): void {
    $source = new ClientAwareRefreshRequest($explicitAuth ? 'token' : null);
    $previous = new TestClient(new ClientConfig(baseUrl: 'https://previous.test'), new MockTransport());
    $source->setClient($previous);
    $dependency = $wrapped ? $source->withTimeout(9) : $source;
    $auth = new class ($dependency) implements AuthenticatorInterface {
        private bool $refresh = true;
        public function __construct(private RequestInterface $dependency) {}
        public function shouldRefresh(): bool { return $this->refresh; }
        public function authenticate(PreparedRequest $request): PreparedRequest { return $request->withHeader('Authorization', 'Bearer resource'); }
        public function getRefreshRequest(): ?RequestInterface { return $this->dependency; }
        public function processTokenResponse(ResponseDtoInterface $response): void { $this->refresh = false; }
    };
    $transport = new MockTransport();
    $transport->fake([
        ClientAwareRefreshRequest::class => MockResponse::success(['token' => 'new']),
        ResourceRequest::class => MockResponse::success(),
    ]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: $auth, authScopes: ['token' => new BearerAuthenticator('dependency')]), $transport);
    $registry = new ClientRegistry();
    $registry->register($client, 'ApiSutra\\Tests\\Stubs\\Auth\\OAuth2');
    ContainerProviderRegistry::set(new MapContainerProvider([ClientResolverInterface::class => new ClientResolver($registry)]));

    $result = $client->send(new ResourceRequest())->raw();
    expect($result->isSuccess())->toBeTrue()->and($transport->getRecorded())->toHaveCount(2);
    $sent = $transport->getRecorded()[0];
    expect($sent->headers['X-Client-Method'])->toBe('https://api.test')
        ->and($sent->headers['X-Client-Property'])->toBe('https://api.test')
        ->and($sent->headers['Authorization'] ?? null)->toBe($explicitAuth ? 'Bearer dependency' : null)
        ->and($sent->transportOptions->timeoutMs)->toBe($wrapped ? 9000 : 7000)
        ->and($source->getClient())->toBe($previous)->and($source->getContext()->client)->toBeNull();
    // Явная постоянная привязка по-прежнему проверяет владение namespace.
    expect(fn () => $source->setClient($client))->toThrow(ConfigurationException::class);
})->with([false, true])->with([false, true]);

it('изолирует клиент одного request между Fiber и восстанавливает постоянную привязку', function (): void {
    $transport = new MockTransport();
    $transport->fake([ClientAwareRefreshRequest::class => function (ClientAwareRefreshRequest $request): MockResponse {
        $client = $request->getClient();
        AsyncTask::current()->runtime->sleep(5);
        expect($request->getClient())->toBe($client);
        return MockResponse::success(['token' => $client->getConfig()->baseUrl]);
    }]);
    $a = new TestClient(new ClientConfig(baseUrl: 'https://a.test'), $transport);
    $b = new TestClient(new ClientConfig(baseUrl: 'https://b.test'), $transport);
    $request = new ClientAwareRefreshRequest();
    $request->setClient($a);
    $results = Utils::all([$a->sendAsync($request), $b->sendAsync($request)])->wait();
    expect($results[0]->dataOrFail()->token)->toBe('https://a.test')
        ->and($results[1]->dataOrFail()->token)->toBe('https://b.test')
        ->and($request->getClient())->toBe($a)->and($request->getContext()->client)->toBeNull();
});

it('вложенный вызов того же request восстанавливает клиент родителя', function (): void {
    $nested = false;
    $transport = new MockTransport();
    $a = new TestClient(new ClientConfig(baseUrl: 'https://a.test'), $transport);
    $b = new TestClient(new ClientConfig(baseUrl: 'https://b.test'), $transport);
    $transport->fake([ClientAwareRefreshRequest::class => function (ClientAwareRefreshRequest $request) use (&$nested, $a, $b): MockResponse {
        if (!$nested) {
            $nested = true;
            expect($request->getClient())->toBe($a);
            expect($b->send($request)->dataOrFail()->token)->toBe('https://b.test');
            expect($request->getClient())->toBe($a);
        }
        return MockResponse::success(['token' => $request->getClient()->getConfig()->baseUrl]);
    }]);
    $request = new ClientAwareRefreshRequest();
    expect($a->send($request)->dataOrFail()->token)->toBe('https://a.test')
        ->and($request->hasClient())->toBeFalse()->and($request->getContext()->client)->toBeNull();
});
