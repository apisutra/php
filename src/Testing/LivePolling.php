<?php

declare(strict_types=1);

namespace ApiSutra\Testing;

use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Core\RuntimeException;
use ApiSutra\Timing\CooperativeSleeper;
use BackedEnum;
use UnitEnum;
use ValueError;

/**
 * Polling-хелпер для async/polling flow в live-тестах.
 *
 * Вызовы внутри callback должны использовать ->withoutCache(),
 * т.к. статус меняется во времени (pending → ready).
 */
final readonly class LivePolling
{
    /**
     * @template T
     * @param callable(): T $fetch
     * @param callable(T): bool $isReady
     * @return T
     */
    public static function waitUntil(
        callable $fetch,
        callable $isReady,
        int $timeoutSeconds = 60,
        int $intervalMilliseconds = 1000,
        ?string $timeoutMessage = null,
        ?LocalizationConfig $localization = null,
    ): mixed {
        $message = $timeoutMessage ?? new Message('testing.await_timeout_exceeded');
        $startedAt = microtime(true);
        $last = null;
        $sleeper = new CooperativeSleeper();

        while ((microtime(true) - $startedAt) < $timeoutSeconds) {
            $last = $fetch();
            if ($isReady($last)) {
                return $last;
            }

            if ($intervalMilliseconds < 0) {
                throw new ValueError('intervalMilliseconds must be greater than or equal to 0');
            }
            $sleeper->sleepMs($intervalMilliseconds);
        }

        throw new RuntimeException(self::buildTimeoutMessage(
            timeoutMessage: $message,
            timeoutSeconds: $timeoutSeconds,
            intervalMilliseconds: $intervalMilliseconds,
            lastValue: $last,
        ), localization: $localization);
    }

    private static function buildTimeoutMessage(
        string|Message $timeoutMessage,
        int $timeoutSeconds,
        int $intervalMilliseconds,
        mixed $lastValue,
    ): Message {
        return new Message('testing.polling_timeout_details', [
            'message' => $timeoutMessage,
            'timeout' => $timeoutSeconds,
            'interval' => $intervalMilliseconds,
            'last' => $lastValue === null ? 'null' : self::describeLastValue($lastValue),
        ]);
    }

    private static function describeLastValue(mixed $value): string
    {
        if (is_object($value)) {
            $vars = get_object_vars($value);
            $parts = ['class=' . $value::class];

            foreach (['id', 'docflowId', 'monitoringId', 'orderId', 'queryNum', 'taskId'] as $key) {
                if (isset($vars[$key]) && is_scalar($vars[$key])) {
                    $parts[] = sprintf('%s=%s', $key, (string) $vars[$key]);
                    break;
                }
            }

            foreach (['state', 'status', 'docflowState', 'monitoringState'] as $key) {
                if (!isset($vars[$key])) {
                    continue;
                }
                $state = $vars[$key];
                if ($state instanceof BackedEnum) {
                    $parts[] = sprintf('%s=%s', $key, (string) $state->value);
                } elseif ($state instanceof UnitEnum) {
                    $parts[] = sprintf('%s=%s', $key, $state->name);
                } elseif (is_scalar($state)) {
                    $parts[] = sprintf('%s=%s', $key, (string) $state);
                }
                break;
            }

            return implode(', ', $parts);
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return is_string($encoded) ? substr($encoded, 0, 200) . (strlen($encoded) > 200 ? '...' : '') : 'array';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return gettype($value);
    }
}
