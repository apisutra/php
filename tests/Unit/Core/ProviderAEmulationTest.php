<?php

declare(strict_types=1);

use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Tests\Stubs\ProviderA\Dto\ProviderASyncResponseDto;
use ApiSutra\Tests\Stubs\ProviderA\Enums\ProviderAErrorCode;
use ApiSutra\Tests\Stubs\ProviderA\Enums\ProviderAResultCode;
use ApiSutra\Tests\Stubs\ProviderA\ProviderAStatusMap;

describe('Provider A emulation', function () {
    it('гидрирует синхронный ответ в DTO', function () {
        $dto = ProviderASyncResponseDto::from([
            'result_code' => 'ok',
            'operation_token' => 'op-1',
            'data' => ['id' => '123'],
        ]);

        expect($dto->resultCode)->toBe(ProviderAResultCode::Ok);
        expect($dto->errorCode)->toBeNull();
        expect($dto->operationToken)->toBe('op-1');
        expect($dto->data)->toBe(['id' => '123']);
    });

    it('маппит result_code в ResultStatus', function () {
        expect(ProviderAStatusMap::mapResult(ProviderAResultCode::Ok))
            ->toBe(ResultStatus::SUCCESS);
        expect(ProviderAStatusMap::mapResult(ProviderAResultCode::Warning))
            ->toBe(ResultStatus::PARTIAL);
        expect(ProviderAStatusMap::mapResult(ProviderAResultCode::NoData))
            ->toBe(ResultStatus::SUCCESS);
    });

    it('маппит error_code в ResultStatus::FAILED', function () {
        expect(ProviderAStatusMap::mapError(ProviderAErrorCode::InvalidInput))
            ->toBe(ResultStatus::FAILED);
        expect(ProviderAStatusMap::mapError(ProviderAErrorCode::NotFound))
            ->toBe(ResultStatus::FAILED);
        expect(ProviderAStatusMap::mapError(ProviderAErrorCode::ProviderError))
            ->toBe(ResultStatus::FAILED);
        expect(ProviderAStatusMap::mapError(ProviderAErrorCode::Timeout))
            ->toBe(ResultStatus::FAILED);
    });
});
