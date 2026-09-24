<?php

declare(strict_types=1);

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Errors\RequestError;

describe('ResultCollection', function () {
    it('суммирует статусы и формирует BatchMeta', function () {
        $results = ResultCollection::make([
            makeResult(ResultStatus::SUCCESS, 'A'),
            makeResult(ResultStatus::FAILED, 'B'),
            makeResult(ResultStatus::PARTIAL, 'C'),
        ]);

        $summary = $results->summarize();
        $meta = $results->toBatchMeta();

        expect($summary->total)->toBe(3);
        expect($summary->successful)->toBe(1);
        expect($summary->failed)->toBe(1);
        expect($summary->partial)->toBe(1);
        expect($summary->status)->toBe(ResultStatus::PARTIAL);

        expect($meta->total)->toBe(3);
        expect($meta->successful)->toBe(1);
        expect($meta->failed)->toBe(1);
        expect($meta->partial)->toBe(1);
    });

    it('возвращает результаты по классу', function () {
        $results = ResultCollection::make([
            makeResult(ResultStatus::SUCCESS, 'X'),
            makeResult(ResultStatus::FAILED, 'Y'),
            makeResult(ResultStatus::SUCCESS, 'X'),
        ]);

        $byClass = $results->getByClass('X');

        expect($byClass->countTotal())->toBe(2);
        expect($byClass->successful()->countTotal())->toBe(2);
    });
});

function makeResult(ResultStatus $status, string $class): ExecutionResult
{
    $errors = [];
    if ($status === ResultStatus::FAILED) {
        $errors[] = new RequestError(
            code: ErrorCode::ServerError,
            message: 'Ошибка',
            requestClass: $class,
        );
    }

    return new ExecutionResult(
        data: null,
        status: $status,
        errors: new ErrorCollection($errors),
        requestClass: $class,
    );
}
