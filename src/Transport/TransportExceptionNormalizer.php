<?php

declare(strict_types=1);

namespace ApiSutra\Transport;

use ApiSutra\Exceptions\Transport\ConnectionException;
use ApiSutra\Exceptions\Transport\InvalidRequestException;
use ApiSutra\Exceptions\Transport\TimeoutException;
use ApiSutra\Exceptions\Transport\TransportException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Throwable;

/** Нормализует только известные транспортные контракты, сохраняя исходную причину. */
final class TransportExceptionNormalizer
{
    public static function normalize(Throwable $exception): Throwable
    {
        if ($exception instanceof TransportException) {
            return $exception;
        }
        // Guzzle может представить сетевую ошибку отправки/чтения как PSR request exception.
        if ($exception instanceof RequestException || $exception instanceof ConnectException) {
            $errno = $exception->getHandlerContext()['errno'] ?? null;
            if ($errno === 28) {
                return new TimeoutException($exception->getMessage(), (int) $exception->getCode(), $exception);
            }
            if (in_array($errno, [52, 55, 56], true)) {
                return new ConnectionException($exception->getMessage(), (int) $exception->getCode(), $exception);
            }
        }
        if ($exception instanceof NetworkExceptionInterface) {
            return new ConnectionException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }
        if ($exception instanceof RequestExceptionInterface) {
            return new InvalidRequestException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }
        if ($exception instanceof ClientExceptionInterface) {
            return new TransportException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }

        return $exception;
    }
}
