<?php

declare(strict_types=1);

use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Tests\Stubs\ProviderB\Dto\ProviderBAsyncResponseDto;
use ApiSutra\Tests\Stubs\ProviderB\Dto\ProviderBEventsResponseDto;
use ApiSutra\Tests\Stubs\ProviderB\Enums\ProviderBErrorCode;
use ApiSutra\Tests\Stubs\ProviderB\Enums\ProviderBStatus;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\ProviderB\ProviderBStatusMap;
use ApiSutra\Tests\Stubs\ProviderB\Requests\ProviderBStatusRequest;
use ApiSutra\Tests\Support\TestClientFactory;

describe('Provider B emulation', function () {
    it('гидрирует асинхронный ответ в DTO', function () {
        $dto = ProviderBAsyncResponseDto::from([
            'status' => 'in_progress',
            'operation_id' => 'op-10',
            'data' => ['stage' => 1],
        ]);

        expect($dto->status)->toBe(ProviderBStatus::InProgress);
        expect($dto->errorCode)->toBeNull();
        expect($dto->operationId)->toBe('op-10');
        expect($dto->data)->toBe(['stage' => 1]);
    });

    it('маппит статус в ResultStatus', function () {
        expect(ProviderBStatusMap::mapStatus(ProviderBStatus::Accepted))
            ->toBe(ResultStatus::PARTIAL);
        expect(ProviderBStatusMap::mapStatus(ProviderBStatus::Ready))
            ->toBe(ResultStatus::SUCCESS);
        expect(ProviderBStatusMap::mapStatus(ProviderBStatus::Failed))
            ->toBe(ResultStatus::FAILED);
    });

    it('маппит error_code в ResultStatus::FAILED', function () {
        expect(ProviderBStatusMap::mapError(ProviderBErrorCode::InvalidInput))
            ->toBe(ResultStatus::FAILED);
        expect(ProviderBStatusMap::mapError(ProviderBErrorCode::ProviderError))
            ->toBe(ResultStatus::FAILED);
    });

    it('гидрирует ленту событий', function () {
        $dto = ProviderBEventsResponseDto::from([
            'events' => [
                ['status' => 'accepted', 'at' => '2026-01-28T10:00:00Z'],
                ['status' => 'ready', 'at' => '2026-01-28T10:00:10Z'],
            ],
        ]);

        expect($dto->events)->toHaveCount(2);
        expect($dto->events[0]['status'])->toBe('accepted');
        expect($dto->events[1]['status'])->toBe('ready');
    });

    it('поддерживает последовательность статусов для polling', function () {
        $client = TestClientFactory::make([
            ProviderBStatusRequest::class => MockResponse::sequence([
                MockResponse::success(['status' => 'accepted', 'operation_id' => 'op-55']),
                MockResponse::success(['status' => 'in_progress', 'operation_id' => 'op-55']),
                MockResponse::success(['status' => 'ready', 'operation_id' => 'op-55']),
            ]),
        ]);

        $request = new ProviderBStatusRequest('op-55');
        $request->setClient($client);

        $statuses = [
            $request->withoutCache()->send()->raw()->data->status,
            $request->withoutCache()->send()->raw()->data->status,
            $request->withoutCache()->send()->raw()->data->status,
        ];

        expect($statuses)->toBe([
            ProviderBStatus::Accepted,
            ProviderBStatus::InProgress,
            ProviderBStatus::Ready,
        ]);
    });
});
