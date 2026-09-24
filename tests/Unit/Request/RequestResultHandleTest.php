<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\Result\ContinuationTokenExtractorInterface;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResolvedResult;
use ApiSutra\Result\ResultHandle;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Tests\Stubs\Request\TestResolvedResult;
use ApiSutra\Tests\Stubs\Request\TestResolvedResultFactory;

describe('ResultHandle и resolved-результаты', function () {
    it('send возвращает ResultHandle и resolved даёт дефолтный результат', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'A']),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $handle = $request->send();
        expect($handle)->toBeInstanceOf(ResultHandle::class);

        $resolved = $handle->resolved();
        expect($resolved)->toBeInstanceOf(ResolvedResult::class);
        expect($resolved->data())->toBeInstanceOf(SimpleResponseDto::class)
            ->and($resolved->continuationToken())->toBeNull()
            ->and($handle->continuationToken())->toBeNull();
    });

    it('resolved использует кастомную фабрику результата', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['id' => 2, 'name' => 'B']),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                resolvedResultFactory: new TestResolvedResultFactory(),
            ),
            $transport,
        );

        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $resolved = $request->send()->resolved();
        expect($resolved)->toBeInstanceOf(TestResolvedResult::class);
        expect($resolved->marker())->toBe('custom');
    });

    it('dataOrFail возвращает данные при успехе', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['id' => 3, 'name' => 'C']),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $data = $request->send()->dataOrFail();
        expect($data)->toBeInstanceOf(SimpleResponseDto::class);
        expect($data->id)->toBe(3);
    });

    it('dataOrFail бросает исключение при ошибке', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::serverError(),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        expect(fn () => $request->send()->dataOrFail())
            ->toThrow(SdkException::class);
    });

    it('ResultHandle возвращает continuation token через configured extractor', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['id' => 4, 'name' => 'D']),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                continuationTokenExtractor: new class implements ContinuationTokenExtractorInterface
                {
                    #[\Override]
                    public function extract(ExecutionResult $result): ?string
                    {
                        return 'token-from-config';
                    }
                },
            ),
            $transport,
        );

        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $handle = $request->send();

        expect($handle->continuationToken())->toBe('token-from-config')
            ->and($handle->continuationTokenOrFail())->toBe('token-from-config')
            ->and($handle->resolved()->continuationToken())->toBe('token-from-config');
    });
});
