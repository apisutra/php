<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Error;

use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Core\AbstractClient;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Exceptions\Request\BadGatewayException;
use ApiSutra\Exceptions\Request\ClientException;
use ApiSutra\Exceptions\Request\ServerException;
use ApiSutra\Exceptions\Request\ForbiddenException;
use ApiSutra\Exceptions\Request\GatewayTimeoutException;
use ApiSutra\Exceptions\Request\InternalServerException;
use ApiSutra\Exceptions\Request\NotFoundException;
use ApiSutra\Exceptions\Request\PaymentRequiredException;
use ApiSutra\Exceptions\Request\RateLimitException;
use ApiSutra\Exceptions\Request\RequestException;
use ApiSutra\Exceptions\Request\RequestTimeoutException;
use ApiSutra\Exceptions\Request\ServiceUnavailableException;
use ApiSutra\Exceptions\Request\UnauthorizedException;
use ApiSutra\Exceptions\Request\UnprocessableEntityException;
use ApiSutra\VO\Http\ProviderResponse;
use Throwable;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Timing\SystemClock;
use ApiSutra\Retry\RetryAfterDelay;

final readonly class ErrorPolicy
{
    private RetryAfterDelay $retryAfterDelay;

    public function __construct(
        private ?AbstractClient $client = null,
        ClockInterface $clock = new SystemClock(),
    ) {
        $this->retryAfterDelay = new RetryAfterDelay($clock->unixTime(...));
    }

    public function hasRequestFailed(RequestInterface $request, ?ProviderResponse $response): bool
    {
        if ($response === null) {
            return true;
        }

        if ($request instanceof AbstractRequest && $request->hasRequestFailedInternal($response)) {
            return true;
        }

        if ($this->client !== null) {
            return $this->client->hasRequestFailedInternal($response);
        }

        return $this->hasRequestFailedInternal($response);
    }

    private function hasRequestFailedInternal(ProviderResponse $response): bool
    {
        return $response->status >= 400;
    }

    private function shouldRetryInternal(ProviderResponse $response, int $attempt): bool
    {
        return false;
    }

    public function getRequestExceptionInternal(RequestInterface $request, ProviderResponse $response, ?ClockInterface $clock = null): ?Throwable
    {
        if ($request instanceof AbstractRequest) {
            $custom = $request->getRequestExceptionInternal($response);
            if ($custom !== null) {
                return $custom;
            }
        }

        if ($this->client !== null) {
            $custom = $this->client->getRequestExceptionInternal($response);
            if ($custom !== null) {
                return $custom;
            }
        }

        return $this->mapException($response, $clock);
    }

    private function mapException(ProviderResponse $response, ?ClockInterface $clock): ?RequestException
    {
        $message = $response->errorMessage();

        return match (true) {
            $response->status === 401 => new UnauthorizedException($message, $response),
            $response->status === 402 => new PaymentRequiredException($message, $response),
            $response->status === 403 => new ForbiddenException($message, $response),
            $response->status === 404 => new NotFoundException($message, $response),
            $response->status === 408 => new RequestTimeoutException($message, $response),
            $response->status === 422 => new UnprocessableEntityException($message, $response),
            $response->status === 429 => new RateLimitException(
                $message,
                $response,
                $this->retryAfterDelay->seconds($response->header('Retry-After'), $clock?->unixTime()),
            ),
            $response->status === 500 => new InternalServerException($message, $response),
            $response->status === 502 => new BadGatewayException($message, $response),
            $response->status === 503 => new ServiceUnavailableException($message, $response),
            $response->status === 504 => new GatewayTimeoutException($message, $response),
            $response->status >= 400 && $response->status < 500 => new ClientException($message, $response),
            $response->status >= 500 => new ServerException($message, $response),
            default => null,
        };
    }
}
