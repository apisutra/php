<?php

declare(strict_types=1);

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Contracts\Interfaces\Localization\LocalizableExceptionInterface;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\Exceptions\Auth\AuthDependencyException;
use ApiSutra\Exceptions\Auth\AuthLockBackendException;
use ApiSutra\Exceptions\Auth\AuthRefreshFailedException;
use ApiSutra\Exceptions\Auth\AuthRefreshLockTimeoutException;
use ApiSutra\Exceptions\Continuation\ContinuationAwaitException;
use ApiSutra\Exceptions\ControlFlow\EarlyReturnException;
use ApiSutra\Exceptions\ControlFlow\RetryableException;
use ApiSutra\Exceptions\Files\FileTransferException;
use ApiSutra\Exceptions\RateLimiting\RateLimitBackendException;
use ApiSutra\Exceptions\Request\RateLimitException;
use ApiSutra\Exceptions\Request\RequestException;
use ApiSutra\Exceptions\Retry\RetrySafetyException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Exceptions\Serialization\ResponseDecodingException;
use ApiSutra\Exceptions\Testing\RecordingException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Exceptions\Transport\TransportException;
use ApiSutra\Exceptions\Validation\ValidationException;
use ApiSutra\Localization\Message;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Http\ProviderResponse;

it('сохраняет специализированные данные всех фабрик локализованных исключений', function (): void {
    $cause = new RuntimeException('synthetic cause');
    $result = new ExecutionResult(null, ResultStatus::FAILED, new ErrorCollection([]));
    $response = new ProviderResponse(401, [], '{}', new PreparedRequest(HttpMethod::GET, 'https://example.test'), 0);
    $message = new Message('errors.execution_error');
    $exceptions = [
        new AuthDependencyException($result), new AuthLockBackendException($cause),
        new AuthRefreshFailedException($response, $result, $cause), new AuthRefreshLockTimeoutException(),
        new ContinuationAwaitException($message, 'final_not_ready', 2, $result),
        new EarlyReturnException(['count' => 2]), new RetryableException(retryAfter: 7, maxAttempts: 3),
        new FileTransferException('write', 12, true, $cause), new RateLimitBackendException($cause, $response),
        new RateLimitException($message, $response, 8, 429, lastResponse: $response),
        new RequestException($message, $response, 400), new RetrySafetyException($cause),
        HydrationException::invalidValue('invalid_field_type', 'int', 'string', 'child.id', $cause),
        new ResponseDecodingException($message, 9, $cause, 'invalid_json'),
        new RecordingException($response, $cause), new ExecutionDeadlineException('http', $cause, $response, 4, true),
        new TransportException($message, 19, $cause), new ValidationException([], code: 422),
    ];
    foreach ($exceptions as $exception) {
        expect($exception)->toBeInstanceOf(LocalizableExceptionInterface::class);
        $originalMessage = $exception->getMessage();
        $copy = $exception->localized(new LocalizationConfig('ru'));
        expect($copy::class)->toBe($exception::class)
            ->and(get_object_vars($copy))->toBe(get_object_vars($exception))
            ->and($copy->getCode())->toBe($exception->getCode())
            ->and($copy->getPrevious())->toBe($exception)
            ->and($copy->getMessage())->not->toBe($originalMessage)
            ->and($exception->getMessage())->toBe($originalMessage);
    }
});
