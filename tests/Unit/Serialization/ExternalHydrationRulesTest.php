<?php

declare(strict_types=1);

use ApiSutra\Config\HydrationConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Tests\Support\HydrationRulesFixture;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\SourcePathKind;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\HydrationRules\RecordDto;
use ApiSutra\Tests\Stubs\HydrationRules\ReportDto;
use ApiSutra\Tests\Stubs\HydrationRules\ReportRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

it('гидратирует граф plain readonly DTO и сохраняет остатки через standalone и Returns', function (bool $http): void {
    $rules = HydrationRulesFixture::rules();
    $payload = HydrationRulesFixture::payload();
    $before = $payload;
    if ($http) {
        $transport = new MockTransport();
        $transport->preventStrayRequests();
        $transport->fake([ReportRequest::class => MockResponse::success(['data' => $payload])]);
        $client = new TestClient(new ClientConfig(baseUrl: 'https://rules.test', hydration: new HydrationConfig(rules: $rules)), $transport);
        $dto = $client->send(new ReportRequest())->dataOrFail();
        $transport->assertSent(ReportRequest::class, times: 1);
    } else {
        $dto = Hydrator::forRules($rules)->hydrate($payload, ReportDto::class);
    }
    expect($dto)->toBeInstanceOf(ReportDto::class)->and($dto->id)->toBe(7)
        ->and($dto->owner->id)->toBe(11)->and($dto->owner->extra)->toBe(['future' => false])
        ->and($dto->items[0])->toBeInstanceOf(RecordDto::class)->and($dto->items[0]->id)->toBe(12)
        ->and($dto->ids)->toBe([1, 2])->and($dto->count)->toBeNull()
        ->and($dto->extra)->toBe([
            'legacy_id' => 99, 'rows' => [['sourceKey' => 0, 'remainder' => ['meta' => ['future' => 0]]]], 'future' => null,
        ])->and($payload)->toBe($before);
})->with([false, true]);

it('возвращает точные DTO и source пути после mapping, each и unwrap', function (bool $http): void {
    $rules = HydrationRulesFixture::rules();
    $payload = HydrationRulesFixture::payload();
    $payload['rows'][] = ['value' => ['record_id' => '13']];
    if ($http) {
        $transport = new MockTransport();
        $transport->fake([ReportRequest::class => MockResponse::success(['data' => $payload])]);
        $client = new TestClient(new ClientConfig(baseUrl: 'https://rules.test', hydration: new HydrationConfig(rules: $rules)), $transport);
        $result = $client->send(new ReportRequest())->raw();
        $error = $result->exception;
        expect($result->response->status)->toBe(200);
        $transport->assertSent(ReportRequest::class, times: 1);
    } else {
        $error = HydrationRulesFixture::error(fn () => Hydrator::forRules($rules)->hydrate($payload, ReportDto::class));
    }
    expect($error)->toBeInstanceOf(HydrationException::class)
        ->and($error->reason)->toBe('invalid_field_type')
        ->and($error->path)->toBe(($http ? 'data.' : '') . 'items[1].id')
        ->and($error->sourcePath)->toBe(($http ? '/data' : '') . '/rows/1/value/record_id')
        ->and($error->sourcePathKind)->toBe(SourcePathKind::Resolved);
})->with([false, true]);
