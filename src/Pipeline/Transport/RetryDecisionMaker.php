<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Transport;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Concurrency\RetrySafetyPolicyInterface;
use ApiSutra\Exceptions\Retry\RetrySafetyException;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Core\AbstractClient;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Auth\AuthLockBackendException;
use ApiSutra\Exceptions\Auth\AuthRefreshLockTimeoutException;
use ApiSutra\Exceptions\ControlFlow\RetryableException;
use ApiSutra\Exceptions\Files\FileTransferException;
use ApiSutra\Exceptions\Transport\TransportException;
use ApiSutra\Pipeline\Error\ErrorPolicy;
use ApiSutra\VO\Http\ProviderResponse;
use Throwable;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;

final readonly class RetryDecisionMaker
{
    public function __construct(
        private ClientConfig $config,
        private ErrorPolicy $errorPolicy,
        private ?AbstractClient $client = null,
    ) {
    }

    public function shouldRetry(
        RequestInterface $request,
        ProviderResponse $response,
        int $attempt,
        ?RetryConfig $retryConfig,
        ?ClockInterface $clock = null,
    ): bool {
        if ($retryConfig === null) {
            return false;
        }

        if ($request instanceof AbstractRequest && $request->shouldRetryInternal($response, $attempt)) {
            return true;
        }

        if ($this->client?->shouldRetryInternal($response, $attempt) === true) {
            return true;
        }

        $exception = $this->errorPolicy->getRequestExceptionInternal($request, $response, $clock);
        if ($exception instanceof RetryableException) {
            return true;
        }

        return in_array($response->status, $retryConfig->retryOn, true);
    }

    public function isSafe(
        RequestInterface $request,
        HttpMethod $method,
        ?ProviderResponse $response = null,
        ?Throwable $exception = null,
    ): bool {
        $safe = $request instanceof AbstractRequest ? $request->getRetryAttribute()?->safe : null;
        if ($safe !== null) {
            return $safe;
        }
        if ($request instanceof RetrySafetyPolicyInterface) {
            try {
                $safe = $request->isRetrySafe($method, $response, $exception);
            } catch (Throwable $failure) {
                throw new RetrySafetyException($failure);
            }
        }
        return $safe ?? in_array($method, ($this->config->retry ?? new RetryConfig())->safeMethods, true);
    }

    public function isRetryException(Throwable $exception, RetryConfig $retryConfig): bool
    {
        if ($exception instanceof RetrySafetyException) {
            return false;
        }
        if ($exception instanceof FileTransferException || $exception instanceof AuthLockBackendException || $exception instanceof AuthRefreshLockTimeoutException) {
            return false;
        }
        foreach ($retryConfig->retryExceptions as $class) {
            if ($exception instanceof $class) {
                return true;
            }
        }

        // Явная настройка исходного PSR-класса продолжает работать после нормализации.
        return $exception instanceof TransportException && $exception->getPrevious() !== null
            && $this->isRetryException($exception->getPrevious(), $retryConfig);
    }
}
