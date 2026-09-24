<?php

declare(strict_types=1);

use ApiSutra\Result\ResolvedResultInterface;
use ApiSutra\Testing\LiveResultAssertions;
use ApiSutra\Tests\Stubs\Request\TestResolvedResult;
use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Errors\RequestError;

function makeSuccessResolved(object $data): ResolvedResultInterface
{
    $result = new ExecutionResult(
        data: $data,
        status: ResultStatus::SUCCESS,
        errors: new ErrorCollection([]),
    );

    return new TestResolvedResult($result);
}

function makeFailedResolved(?string $code = null, ?string $message = null, ?int $status = null): ResolvedResultInterface
{
    $result = new ExecutionResult(
        data: null,
        status: ResultStatus::FAILED,
        errors: new ErrorCollection([
            new RequestError(
                code: ErrorCode::ServerError,
                message: $message ?? 'error',
                context: [],
            ),
        ]),
    );

    $resolved = new TestResolvedResult($result);

    // TestResolvedResult возвращает null для errorCode/Status — нужен stub с реальными значениями
    return new class($resolved, $code ?? 'provider_error', $message ?? 'error', $status ?? 500) implements ResolvedResultInterface {
        public function __construct(
            private ResolvedResultInterface $inner,
            private string $errorCode,
            private string $errorMessage,
            private int $errorStatus,
        ) {}

        public function data(): mixed
        {
            return $this->inner->data();
        }

        public function isSuccess(): bool
        {
            return $this->inner->isSuccess();
        }

        public function isPartial(): bool
        {
            return $this->inner->isPartial();
        }

        public function isFailed(): bool
        {
            return $this->inner->isFailed();
        }

        public function hasData(): bool
        {
            return $this->inner->hasData();
        }

        public function hasErrors(): bool
        {
            return $this->inner->hasErrors();
        }

        public function errors(): \ApiSutra\Collections\ErrorCollection
        {
            return $this->inner->errors();
        }

        public function errorViews(): array
        {
            return $this->inner->errorViews();
        }

        public function error(): ?\ApiSutra\VO\Errors\ClientError
        {
            return $this->inner->error();
        }

        public function errorContexts(): array
        {
            return $this->inner->errorContexts();
        }

        public function errorContext(): ?object
        {
            return $this->inner->errorContext();
        }

        public function errorCode(): ?string
        {
            return $this->errorCode;
        }

        public function errorMessage(): ?string
        {
            return $this->errorMessage;
        }

        public function errorStatus(): ?int
        {
            return $this->errorStatus;
        }

        public function errorRetryable(): ?bool
        {
            return $this->inner->errorRetryable();
        }

        public function errorCategory(): ?string
        {
            return $this->inner->errorCategory();
        }

        public function errorProviderTraceId(): ?string
        {
            return $this->inner->errorProviderTraceId();
        }

        public function continuationToken(): ?string
        {
            return $this->inner->continuationToken();
        }

        public function continuationTokenOrFail(): string
        {
            return $this->inner->continuationTokenOrFail();
        }

        public function message(): ?string
        {
            return $this->inner->message();
        }

        public function result(): \ApiSutra\Result\ExecutionResult
        {
            return $this->inner->result();
        }
    };
}

it('assertSuccess не бросает при успешном результате', function (): void {
    $resolved = makeSuccessResolved((object) ['id' => 1]);

    LiveResultAssertions::assertSuccess($resolved, 'test-op');

    expect(true)->toBeTrue();
});

it('assertSuccess бросает при неуспешном результате', function (): void {
    $resolved = makeFailedResolved('err_code', 'Something failed', 400);

    LiveResultAssertions::assertSuccess($resolved, 'my-operation');
})->throws(RuntimeException::class, 'my-operation');

it('assertDataInstanceOf не бросает при правильном типе', function (): void {
    $data = new \stdClass();
    $data->id = 'x';
    $resolved = makeSuccessResolved($data);

    LiveResultAssertions::assertDataInstanceOf($resolved, \stdClass::class, 'get-x');

    expect(true)->toBeTrue();
});

it('assertDataInstanceOf бросает при неверном типе', function (): void {
    $resolved = makeSuccessResolved((object) ['id' => 1]); // stdClass

    LiveResultAssertions::assertDataInstanceOf($resolved, \ArrayObject::class, 'get-user');
})->throws(RuntimeException::class, 'get-user');

it('assertDataInstanceOf бросает при провале до проверки типа', function (): void {
    $resolved = makeFailedResolved();

    LiveResultAssertions::assertDataInstanceOf($resolved, \stdClass::class, 'op');
})->throws(RuntimeException::class);
