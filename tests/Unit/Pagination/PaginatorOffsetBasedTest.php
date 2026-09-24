<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\OffsetPaginatedRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

describe('Paginator offset-based', function () {
    it('требует limit и доставляет ошибку согласно throwOnErrors', function (bool $throw): void {
        $transport = new MockTransport();
        $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', throwOnErrors: $throw), $transport);
        $request = new OffsetPaginatedRequest()->setClient($client);
        if ($throw) {
            expect(fn () => $request->paginate()->pages(1))
                ->toThrow(ConfigurationException::class, 'Offset-based pagination requires limit');
        } else {
            $result = $request->paginate()->pages(1);
            expect($result->isFailed())->toBeTrue()->and($result->exception)->toBeInstanceOf(ConfigurationException::class);
        }
        expect($transport->getRecorded())->toBeEmpty();
    })->with([false, true]);

    it('преобразует page в offset', function () {
        $transport = new MockTransport();
        $transport->fake([
            OffsetPaginatedRequest::class => MockResponse::success([
                'data' => [],
                'meta' => [
                    'page' => 1,
                    'limit' => 10,
                    'total' => 0,
                    'has_more' => false,
                ],
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://provider.test',
                environment: Environment::Testing,
            ),
            $transport,
        );

        $request = new OffsetPaginatedRequest();
        $request->setClient($client);

        $request->paginate()->withPerPage(10)->pages(1);

        $recorded = $transport->getRecorded();
        expect($recorded)->toHaveCount(1);

        $query = $recorded[0]->meta['query'] ?? [];
        expect($query['offset']['value'] ?? null)->toBe(0);
        expect($query['limit']['value'] ?? null)->toBe(10);
    });
});
