<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Diagnostics\RedactionPolicy;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Pagination\PageRequest;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Tracing\RecordingObserver;
use ApiSutra\Tests\Stubs\Tracing\ObserverProvider;
use ApiSutra\Transport\MockTransport;

it('различает корень пагинации и независимые корни pool и освобождает все исполнения', function (string $mode): void {
    $observer = new RecordingObserver();
    $transport = new MockTransport();
    $transport->fake(['*' => static function ($request): MockResponse {
        $page = $request->getContext()->paginationOptions?->getPage() ?? 1;
        return MockResponse::success(['data' => [$page], 'meta' => ['page' => $page, 'per_page' => 1, 'total' => 20]]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://test.example', containerProvider: new ObserverProvider($observer)), $transport);
    if ($mode === 'pages') {
        $result = (new PageRequest())->setClient($client)->paginate()->withConcurrency(5)->all();
        expect($result->items())->toHaveCount(20);
    } elseif ($mode === 'items') {
        expect(iterator_to_array((new PageRequest())->setClient($client)->paginate()->items()))->toHaveCount(20);
    } else {
        $summary = $client->pool(array_map(static fn () => new PageRequest(), range(1, 20)), 5)->consume();
        expect($summary->total)->toBe(20);
    }
    expect($observer->active)->toBe(0)
        ->and(array_filter($observer->snapshots, static fn ($s) => $s->isRoot()))->toHaveCount($mode === 'pool' ? 20 : 1)
        ->and(array_sum(array_column(array_column($observer->snapshots, 'data'), 'attemptCount')))->toBe(20);
})->with(['pages', 'items', 'pool']);

it('маскирует выбранные поля до observer и изолирует его исключение', function (): void {
    $observer = new RecordingObserver();
    $observer->fail = true;
    $transport = new MockTransport();
    $transport->fake([SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'ok'])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://test.example', containerProvider: new ObserverProvider($observer),
        diagnosticLabel: 'private', redaction: new RedactionPolicy(fields: ['clientLabel'])), $transport);
    expect($client->sendAsync(new SimpleGetRequest('secret'))->wait()->dataOrFail()->id)->toBe(1)
        ->and($observer->snapshots[0]->data['clientLabel'])->toBe('***')->and($observer->active)->toBe(0);
});
