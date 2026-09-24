<?php

declare(strict_types=1);

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Errors\RequestError;

describe('ExecutionResult message', function () {
    it('возвращает null при отсутствии ошибок', function () {
        $result = new ExecutionResult(
            data: ['ok' => true],
            status: ResultStatus::SUCCESS,
            errors: new ErrorCollection([]),
        );

        expect($result->message())->toBeNull();
    });

    it('возвращает сообщение первой ошибки', function () {
        $error = new RequestError(
            code: ErrorCode::ServerError,
            message: 'Ошибка 1',
        );
        $result = new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: new ErrorCollection([$error]),
        );

        expect($result->message())->toBe('Ошибка 1');
    });

    it('возвращает агрегированное сообщение при нескольких ошибках', function () {
        $errorA = new RequestError(
            code: ErrorCode::ServerError,
            message: 'Ошибка A',
        );
        $errorB = new RequestError(
            code: ErrorCode::BadGateway,
            message: 'Ошибка B',
        );
        $result = new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: new ErrorCollection([$errorA, $errorB]),
        );

        expect($result->message())->toBe('Multiple errors: 2. Ошибка A');
    });

    it('сохраняет BC для позиционного exception аргумента конструктора', function () {
        $exception = new \RuntimeException('legacy');

        $result = new ExecutionResult(
            null,
            ResultStatus::FAILED,
            new ErrorCollection([]),
            [],
            null,
            null,
            [],
            null,
            [],
            null,
            $exception,
        );

        expect($result->exception)->toBe($exception)
            ->and($result->response)->toBeNull();
    });
});
