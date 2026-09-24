<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Continuation\ContinuationAwaitOptions;
use ApiSutra\Core\AbstractClient;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Continuation\ContinuationMode;
use ApiSutra\Exceptions\Configuration\ContinuationConfigurationException;
use ApiSutra\Result\ContinuationTokenExtractorInterface;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Testing\MockSequence;
use ApiSutra\Tests\Stubs\Dto\ContinuationFinalDto;
use ApiSutra\Tests\Stubs\Continuation\RootValueStateResolver;
use ApiSutra\Tests\Stubs\Requests\ContinuationPollRequest;
use ApiSutra\Tests\Stubs\Requests\ContinuationStartRequest;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Http\ProviderResponse;

function makeTestContinuationTokenExtractor(): ContinuationTokenExtractorInterface
{
    return new class implements ContinuationTokenExtractorInterface
    {
        #[\Override]
        public function extract(ExecutionResult $result): ?string
        {
            $data = $result->data;
            if (is_array($data)) {
                $token = $data['operationToken'] ?? null;

                return is_string($token) ? $token : null;
            }

            if (is_object($data) && property_exists($data, 'operationToken')) {
                $token = $data->operationToken;

                return is_string($token) ? $token : null;
            }

            $errorToken = $result->errors->first()?->response?->json('operationToken');

            if (is_string($errorToken) && $errorToken !== '') {
                return $errorToken;
            }

            return null;
        }
    };
}

function makePendingAsFailedClient(ClientConfig $config, TransportInterface $transport): AbstractClient
{
    return new class ($config, $transport) extends AbstractClient
    {
        public function __construct(ClientConfig $config, TransportInterface $transport)
        {
            parent::__construct($config, $transport);
        }

        #[\Override]
        protected function hasRequestFailed(ProviderResponse $response): bool
        {
            if (parent::hasRequestFailed($response)) {
                return true;
            }

            $resultCode = $response->json('resultCode');

            if (!is_int($resultCode)) {
                return false;
            }

            return $resultCode !== 0;
        }

        #[\Override]
        protected function getRequestException(ProviderResponse $response): ?Throwable
        {
            $resultCode = $response->json('resultCode');

            if (!is_int($resultCode) || $resultCode === 0) {
                return null;
            }

            return new RuntimeException('provider result code: ' . $resultCode);
        }
    };
}

describe('ResultHandle continuation await', function () {
    it('в режиме auto возвращает immediate финал без polling, если финал уже есть', function () {
        $transport = new MockTransport();
        $transport->fake([
            ContinuationStartRequest::class => MockResponse::success([
                'operationToken' => 'tok-1',
                'data' => ['value' => 'ready'],
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                continuationTokenExtractor: makeTestContinuationTokenExtractor(),
                defaultPollRequest: ContinuationPollRequest::class,
            ),
            $transport,
        );

        $request = new ContinuationStartRequest('x');
        $request->setClient($client);

        $result = $request->send()->await();

        expect($result)->toBeInstanceOf(ContinuationFinalDto::class)
            ->and($result->value)->toBe('ready');
        $transport->assertNotSent(ContinuationPollRequest::class);
    });

    it('в режиме async использует polling до получения финала', function () {
        $transport = new MockTransport();
        $transport->fake([
            ContinuationStartRequest::class => MockResponse::success([
                'operationToken' => 'tok-1',
            ]),
            ContinuationPollRequest::class => new MockSequence([
                MockResponse::success(['operationToken' => 'tok-1']),
                MockResponse::success(['operationToken' => 'tok-2', 'data' => ['value' => 'done']]),
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                continuationTokenExtractor: makeTestContinuationTokenExtractor(),
                defaultPollRequest: ContinuationPollRequest::class,
            ),
            $transport,
        );

        $request = new ContinuationStartRequest('x');
        $request->setClient($client);

        $result = $request
            ->asProviderAsync()
            ->send()
            ->await(new ContinuationAwaitOptions(maxAttempts: 5, intervalMs: 0));

        expect($result)->toBeInstanceOf(ContinuationFinalDto::class)
            ->and($result->value)->toBe('done');
        $transport->assertSent(ContinuationPollRequest::class, times: 2);
    });

    it('awaitByToken использует continuation-контракт source request', function () {
        $transport = new MockTransport();
        $transport->fake([
            ContinuationPollRequest::class => MockResponse::success([
                'operationToken' => 'tok-1',
                'data' => ['value' => 'from-token'],
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                continuationTokenExtractor: makeTestContinuationTokenExtractor(),
                defaultPollRequest: ContinuationPollRequest::class,
            ),
            $transport,
        );

        $result = $client->continuation()->awaitByToken(
            token: 'tok-1',
            sourceRequestClass: ContinuationStartRequest::class,
            options: new ContinuationAwaitOptions(maxAttempts: 3, intervalMs: 0),
        );

        expect($result)->toBeInstanceOf(ContinuationFinalDto::class)
            ->and($result->value)->toBe('from-token');
    });

    it('awaitByTokenAs работает с default poll request', function () {
        $transport = new MockTransport();
        $transport->fake([
            ContinuationPollRequest::class => MockResponse::success([
                'value' => 'typed',
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                continuationTokenExtractor: makeTestContinuationTokenExtractor(),
                defaultPollRequest: ContinuationPollRequest::class,
                continuationStateResolver: new RootValueStateResolver(),
            ),
            $transport,
        );

        $result = $client->continuation()->awaitByTokenAs(
            token: 'tok-1',
            finalType: ContinuationFinalDto::class,
            options: new ContinuationAwaitOptions(maxAttempts: 2, intervalMs: 0),
        );

        expect($result)->toBeInstanceOf(ContinuationFinalDto::class)
            ->and($result->value)->toBe('typed');
    });

    it('awaitByToken бросает continuation configuration exception без continuation-контракта', function () {
        $transport = new MockTransport();
        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                continuationTokenExtractor: makeTestContinuationTokenExtractor(),
                defaultPollRequest: ContinuationPollRequest::class,
            ),
            $transport,
        );

        expect(fn () => $client->continuation()->awaitByToken('tok-1', SimpleGetRequest::class))
            ->toThrow(ContinuationConfigurationException::class, 'ContinuationResult is not declared');
    });

    it('повторный await* на одном handle не запускает polling повторно', function () {
        $transport = new MockTransport();
        $transport->fake([
            ContinuationStartRequest::class => MockResponse::success([
                'operationToken' => 'tok-1',
            ]),
            ContinuationPollRequest::class => MockResponse::success([
                'operationToken' => 'tok-1',
                'data' => ['value' => 'cached'],
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                continuationTokenExtractor: makeTestContinuationTokenExtractor(),
                defaultPollRequest: ContinuationPollRequest::class,
                defaultContinuationMode: ContinuationMode::Async,
            ),
            $transport,
        );

        $request = new ContinuationStartRequest('x');
        $request->setClient($client);

        $handle = $request->send();
        $first = $handle->await(new ContinuationAwaitOptions(maxAttempts: 3, intervalMs: 0));
        $second = $handle->awaitAs(ContinuationFinalDto::class, new ContinuationAwaitOptions(maxAttempts: 3, intervalMs: 0));

        expect($first)->toBeInstanceOf(ContinuationFinalDto::class)
            ->and($second)->toBeInstanceOf(ContinuationFinalDto::class)
            ->and($second->value)->toBe('cached');
        $transport->assertSent(ContinuationPollRequest::class, times: 1);
    });

    it('продолжает polling при failed pending-ответах если token присутствует', function () {
        $transport = new MockTransport();
        $transport->fake([
            ContinuationStartRequest::class => MockResponse::success([
                'resultCode' => -29,
                'operationToken' => 'tok-1',
            ]),
            ContinuationPollRequest::class => new MockSequence([
                MockResponse::success([
                    'resultCode' => -29,
                    'operationToken' => 'tok-1',
                ]),
                MockResponse::success([
                    'resultCode' => 0,
                    'operationToken' => 'tok-1',
                    'data' => ['value' => 'resolved-after-pending'],
                ]),
            ]),
        ]);

        $client = makePendingAsFailedClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                continuationTokenExtractor: makeTestContinuationTokenExtractor(),
                defaultPollRequest: ContinuationPollRequest::class,
            ),
            $transport,
        );

        $request = new ContinuationStartRequest('x');
        $request->setClient($client);

        $result = $request->send()->await(new ContinuationAwaitOptions(maxAttempts: 4, intervalMs: 0));

        expect($result)->toBeInstanceOf(ContinuationFinalDto::class)
            ->and($result->value)->toBe('resolved-after-pending');
        $transport->assertSent(ContinuationPollRequest::class, times: 2);
    });

    it('пробрасывает исходную polling-ошибку когда продолжение невозможно', function () {
        $transport = new MockTransport();
        $transport->fake([
            ContinuationStartRequest::class => MockResponse::success([
                'resultCode' => -29,
                'operationToken' => 'tok-1',
            ]),
            ContinuationPollRequest::class => MockResponse::success([
                'resultCode' => -500,
            ]),
        ]);

        $client = makePendingAsFailedClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                continuationTokenExtractor: makeTestContinuationTokenExtractor(),
                defaultPollRequest: ContinuationPollRequest::class,
            ),
            $transport,
        );

        $request = new ContinuationStartRequest('x');
        $request->setClient($client);

        expect(fn () => $request->send()->await(new ContinuationAwaitOptions(maxAttempts: 2, intervalMs: 0)))
            ->toThrow(RuntimeException::class, 'provider result code: -500');
    });
});
